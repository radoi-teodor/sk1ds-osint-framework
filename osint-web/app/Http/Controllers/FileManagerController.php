<?php

namespace App\Http\Controllers;

use App\Models\FileFolder;
use App\Models\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileManagerController extends Controller
{
    public function index()
    {
        return view('files.index');
    }

    public function apiList(Request $request): JsonResponse
    {
        $folder = $this->normalizePath($request->query('folder', '/'));

        $explicitFolders = FileFolder::where('path', 'LIKE', $folder === '/' ? '/%' : $folder . '/%')
            ->pluck('path')
            ->filter(fn ($p) => $this->parentOf($p) === $folder)
            ->values();

        $implicitFolders = UploadedFile::where('folder', 'LIKE', $folder === '/' ? '/%' : $folder . '/%')
            ->pluck('folder')
            ->unique()
            ->filter(fn ($p) => $p !== $folder && str_starts_with($p, $folder))
            ->map(function ($p) use ($folder) {
                $rel = ltrim(substr($p, strlen($folder)), '/');
                return $folder === '/' ? '/' . explode('/', $rel)[0] : $folder . '/' . explode('/', $rel)[0];
            })
            ->unique();

        $subfolders = $explicitFolders->merge($implicitFolders)->unique()->sort()->values()
            ->map(fn ($p) => ['path' => $p, 'name' => basename($p)]);

        $files = UploadedFile::where('folder', $folder)
            ->orderBy('original_name')
            ->get(['id', 'original_name', 'folder', 'size_bytes', 'mime_type', 'created_at']);

        return response()->json([
            'current' => $folder,
            'parent' => $folder === '/' ? null : ($this->parentOf($folder)),
            'breadcrumbs' => $this->breadcrumbs($folder),
            'folders' => $subfolders,
            'files' => $files,
        ]);
    }

    public function apiCreateFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-. ]+$/'],
        ]);
        $parent = $this->normalizePath($data['parent']);
        $path = $parent === '/' ? '/' . $data['name'] : $parent . '/' . $data['name'];
        FileFolder::firstOrCreate(['path' => $path]);
        return response()->json(['ok' => true, 'path' => $path]);
    }

    public function apiUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'folder' => ['nullable', 'string'],
        ]);
        $folder = $this->normalizePath($request->input('folder', '/'));
        $uploaded = $request->file('file');
        $storagePath = $uploaded->store('uploads', 'local');
        $checksum = hash_file('sha256', Storage::disk('local')->path($storagePath));
        $file = UploadedFile::create([
            'user_id' => $request->user()?->id,
            'folder' => $folder,
            'original_name' => $uploaded->getClientOriginalName(),
            'storage_path' => $storagePath,
            'mime_type' => $uploaded->getClientMimeType(),
            'size_bytes' => $uploaded->getSize(),
            'checksum' => $checksum,
        ]);
        return response()->json(['ok' => true, 'file' => $file]);
    }

    public function apiRenameFile(Request $request, UploadedFile $uploaded_file): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:200']]);
        $uploaded_file->update(['original_name' => $data['name']]);
        return response()->json(['ok' => true]);
    }

    public function apiMoveFile(Request $request, UploadedFile $uploaded_file): JsonResponse
    {
        $data = $request->validate(['folder' => ['required', 'string']]);
        $uploaded_file->update(['folder' => $this->normalizePath($data['folder'])]);
        return response()->json(['ok' => true]);
    }

    public function apiDeleteFile(UploadedFile $uploaded_file): JsonResponse
    {
        Storage::disk('local')->delete($uploaded_file->storage_path);
        $uploaded_file->delete();
        return response()->json(['ok' => true]);
    }

    public function apiRenameFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'old_path' => ['required', 'string'],
            'new_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-. ]+$/'],
        ]);
        $oldPath = $this->normalizePath($data['old_path']);
        $parent = $this->parentOf($oldPath);
        $newPath = $parent === '/' ? '/' . $data['new_name'] : $parent . '/' . $data['new_name'];

        if ($newPath !== $oldPath && (FileFolder::where('path', $newPath)->exists() || UploadedFile::where('folder', $newPath)->exists())) {
            return response()->json(['ok' => false, 'error' => 'A folder with that name already exists'], 409);
        }

        FileFolder::where('path', $oldPath)->update(['path' => $newPath]);
        FileFolder::where('path', 'LIKE', $oldPath . '/%')
            ->get()->each(fn ($f) => $f->update(['path' => $newPath . substr($f->path, strlen($oldPath))]));
        UploadedFile::where('folder', $oldPath)->update(['folder' => $newPath]);
        UploadedFile::where('folder', 'LIKE', $oldPath . '/%')
            ->get()->each(fn ($f) => $f->update(['folder' => $newPath . substr($f->folder, strlen($oldPath))]));

        return response()->json(['ok' => true, 'new_path' => $newPath]);
    }

    public function apiDeleteFolder(Request $request): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);
        $path = $this->normalizePath($data['path']);
        if ($path === '/') {
            return response()->json(['ok' => false, 'error' => 'Cannot delete root'], 422);
        }
        $files = UploadedFile::where('folder', $path)->orWhere('folder', 'LIKE', $path . '/%')->get();
        foreach ($files as $f) {
            Storage::disk('local')->delete($f->storage_path);
            $f->delete();
        }
        FileFolder::where('path', $path)->orWhere('path', 'LIKE', $path . '/%')->delete();
        return response()->json(['ok' => true]);
    }

    public function apiAll(): JsonResponse
    {
        $files = UploadedFile::orderBy('folder')->orderBy('original_name')
            ->get(['id', 'original_name', 'folder', 'size_bytes', 'created_at'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'folder' => $f->folder,
                'display' => ($f->folder === '/' ? '' : $f->folder . '/') . $f->original_name,
                'size_bytes' => $f->size_bytes,
            ]);
        return response()->json(['files' => $files]);
    }

    protected function normalizePath(string $p): string
    {
        $p = '/' . trim(str_replace('\\', '/', $p), '/');
        return preg_replace('#/+#', '/', $p) ?: '/';
    }

    protected function parentOf(string $path): string
    {
        $d = dirname($path);
        return $d === '.' || $d === '' ? '/' : $d;
    }

    protected function breadcrumbs(string $path): array
    {
        if ($path === '/') return [['name' => 'root', 'path' => '/']];
        $parts = explode('/', trim($path, '/'));
        $crumbs = [['name' => 'root', 'path' => '/']];
        $cur = '';
        foreach ($parts as $p) {
            $cur .= '/' . $p;
            $crumbs[] = ['name' => $p, 'path' => $cur];
        }
        return $crumbs;
    }
}
