// Cytoscape-based graph view. Used for both investigation and template graphs.
//
// Expected globals (injected by graphs/show.blade.php):
//   window.GRAPH_CONFIG = {
//     graphId, graphType, apiBase,
//     entityTypes: { [type]: {color, shape, icon, label} },
//     transforms:  [ {name, display_name, input_types, ...} ],
//     templates:   [ {id, title} ]
//   }

(function () {
  const cfg = window.GRAPH_CONFIG;
  if (!cfg) return;

  const isTemplate = cfg.graphType === 'template';
  const api = cfg.apiBase;

  // ---------- style ----------
  const nodeStyle = {
    'background-color': (ele) => styleFor(ele.data('entity_type')).color,
    'border-color': (ele) => styleFor(ele.data('entity_type')).color,
    'border-width': 2,
    'shape': (ele) => styleFor(ele.data('entity_type')).shape || 'round-rectangle',
    'label': 'data(displayLabel)',
    'color': '#e8ffe5',
    'font-family': 'JetBrains Mono, Consolas, monospace',
    'font-size': 11,
    'text-valign': 'bottom',
    'text-margin-y': 6,
    'text-outline-color': '#000',
    'text-outline-width': 2,
    'text-wrap': 'ellipsis',
    'text-max-width': 160,
    'width': 46,
    'height': 46,
  };

  function styleFor(t) {
    return (cfg.entityTypes && cfg.entityTypes[t]) || cfg.entityTypes.unknown || { color: '#888', shape: 'round-rectangle', icon: '?', label: 'Unknown' };
  }

  const cy = cytoscape({
    container: document.getElementById('cy'),
    wheelSensitivity: 0.25,
    textureOnViewport: true,
    hideEdgesOnViewport: true,
    motionBlur: false,
    pixelRatio: 1,
    style: [
      { selector: 'node', style: nodeStyle },
      { selector: 'node:selected', style: { 'border-color': '#fff', 'border-width': 3, 'overlay-color': '#00ff9c', 'overlay-opacity': 0.18 } },
      { selector: 'node[entity_type = "template:input"]', style: { 'background-color': '#ffb000', 'border-color': '#ffb000', 'shape': 'round-diamond', 'width': 56, 'height': 56 } },
      { selector: 'node[entity_type = "template:transform"]', style: { 'background-color': '#1d2620', 'border-color': '#00ff9c', 'shape': 'round-tag', 'width': 72, 'height': 38 } },
      { selector: 'edge', style: {
          'curve-style': 'bezier',
          'width': 1.5,
          'line-color': '#3c5242',
          'target-arrow-color': '#3c5242',
          'target-arrow-shape': 'triangle',
          'opacity': 0.85,
        } },
      { selector: 'edge:selected', style: { 'line-color': '#00ff9c', 'target-arrow-color': '#00ff9c', 'width': 2.5 } },
    ],
    layout: { name: 'preset' },
  });

  // ---------- load graph ----------
  function load() {
    return window.csrfFetch(`${api}`)
      .then((r) => r.json())
      .then((data) => {
        cy.elements().remove();
        const toAdd = [];
        for (const n of data.nodes || []) {
          toAdd.push({ group: 'nodes', data: nodeData(n), position: { x: n.position_x || 0, y: n.position_y || 0 } });
        }
        for (const e of data.edges || []) {
          toAdd.push({ group: 'edges', data: { id: e.cy_id, source: e.source, target: e.target, label: e.label } });
        }
        cy.add(toAdd);
        if (cy.elements().length > 0 && cy.nodes().every((n) => n.position().x === 0 && n.position().y === 0)) {
          cy.layout({ name: 'cose', animate: false }).run();
        }
        cy.fit(undefined, 40);
        drawMinimap();
      });
  }

  function nodeData(n) {
    const iconStyle = styleFor(n.entity_type);
    let display = n.label || n.value || n.cy_id;
    if (display.length > 30) display = display.slice(0, 27) + '...';
    display = (iconStyle.icon || '') + ' ' + display;
    return {
      id: n.cy_id,
      entity_type: n.entity_type,
      value: n.value,
      raw_label: n.label,
      displayLabel: display,
      payload: n.data || {},
    };
  }

  // ---------- node selection & sidebar ----------
  const detailBox = document.getElementById('selected-node');
  cy.on('tap', 'node', (evt) => {
    const n = evt.target;
    showDetail(n);
  });
  cy.on('tap', (evt) => {
    if (evt.target === cy) closeMenu();
  });

  function showDetail(n) {
    if (!detailBox) return;
    const d = n.data();
    detailBox.innerHTML = `
      <div class="field"><div class="label">Type</div><div class="value">${esc(d.entity_type)}</div></div>
      <div class="field"><div class="label">Value</div><div class="value">${esc(d.value || '')}</div></div>
      <div class="field"><div class="label">Cy ID</div><div class="value small">${esc(d.id)}</div></div>
      <div class="field"><button class="btn ghost" onclick="window.graphDeleteNode('${d.id}')">DELETE</button></div>
    `;
  }

  // ---------- presentation mode ----------
  const PRESENT_KEY = `osint.present.${cfg.graphId}`;
  let presentMode = false;
  const graphPage = document.querySelector('.graph-page');
  const presentBtn = document.getElementById('present-toggle');

  function setPresentMode(on) {
    presentMode = !!on;
    if (graphPage) graphPage.classList.toggle('present-mode', presentMode);
    if (presentBtn) {
      presentBtn.classList.toggle('active', presentMode);
      presentBtn.textContent = presentMode ? '■ edit' : '▶ present';
    }
    cy.autoungrabify(presentMode);
    if (presentMode) closeMenu();
    // Layout changed — let Cytoscape re-measure the container.
    setTimeout(() => { cy.resize(); cy.fit(undefined, 40); drawMinimap(); }, 280);
    try { localStorage.setItem(PRESENT_KEY, presentMode ? '1' : '0'); } catch (_) {}
  }

  window.togglePresentMode = () => setPresentMode(!presentMode);

  // Restore saved state
  try {
    if (localStorage.getItem(PRESENT_KEY) === '1') setPresentMode(true);
  } catch (_) {}

  // ---------- context menu ----------
  const menu = document.getElementById('ctx-menu');

  function closeMenu() { if (menu) menu.classList.remove('open'); }

  cy.on('cxttap', 'node', (evt) => {
    if (!menu || presentMode) return;
    const n = evt.target;
    const pos = evt.renderedPosition || evt.position;
    menu.innerHTML = renderMenuForNode(n);
    menu.classList.add('open');

    // Place then clamp inside the canvas area so it doesn't spill offscreen.
    const wrap = document.querySelector('.graph-canvas-wrap').getBoundingClientRect();
    menu.style.left = '0px';
    menu.style.top = '0px';
    const rect = menu.getBoundingClientRect();
    const mw = rect.width;
    const mh = rect.height;
    let left = pos.x + 8;
    let top = pos.y + 8;
    if (left + mw > wrap.width - 8) left = Math.max(8, pos.x - mw - 8);
    if (top + mh > wrap.height - 8) top = Math.max(8, wrap.height - mh - 8);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  });

  function renderMenuForNode(n) {
    const type = n.data('entity_type');
    const value = n.data('value') || '';
    const nodeId = esc(n.data('id'));
    const short = value.length > 30 ? value.slice(0, 27) + '...' : value;

    let html = `<div class="ctx-header">${esc(type)} · ${esc(short)}</div>`;

    if (isTemplate) {
      // Only template:input and template:transform can have next steps chained.
      const compatible = compatibleNextSteps(n);
      if (compatible.length === 0) {
        html += `<div class="ctx-item" style="color:var(--text-dim)">No compatible next steps</div>`;
      } else {
        const byCat = {};
        for (const t of compatible) {
          const cat = t.category || 'other';
          (byCat[cat] ||= []).push(t);
        }
        const cats = Object.keys(byCat).sort();
        for (const cat of cats) {
          html += `<div class="ctx-cat">${esc(cat)}</div>`;
          for (const t of byCat[cat]) {
            const desc = (t.description || '').replace(/"/g, '&quot;');
            const needsKey = (t.required_api_keys || []).length > 0 ? ' 🔑' : '';
            html += `<div class="ctx-item" title="${desc}" onclick="window.templateAddNextStep('${nodeId}','${esc(t.name)}')">+ ${esc(t.display_name || t.name)}${needsKey}</div>`;
          }
        }
      }
      html += `<div class="ctx-cat">node</div>`;
      html += `<div class="ctx-item danger" onclick="window.graphDeleteNode('${nodeId}')">delete step</div>`;
      return html;
    }

    const compatible = (cfg.transforms || []).filter((t) =>
      (t.input_types || []).includes('*') || (t.input_types || []).includes(type)
    );

    if (compatible.length === 0) {
      html += `<div class="ctx-item" style="color:var(--text-dim)">No transforms for this type</div>`;
    } else {
      // group by category
      const byCat = {};
      for (const t of compatible) {
        const cat = t.category || 'other';
        (byCat[cat] ||= []).push(t);
      }
      const cats = Object.keys(byCat).sort();
      for (const cat of cats) {
        html += `<div class="ctx-cat">${esc(cat)}</div>`;
        for (const t of byCat[cat]) {
          const desc = (t.description || '').replace(/"/g, '&quot;');
          const needsKey = (t.required_api_keys || []).length > 0 ? ' 🔑' : '';
          html += `<div class="ctx-item" title="${desc}" onclick="window.runTransform('${nodeId}','${esc(t.name)}')">${esc(t.display_name || t.name)}${needsKey}</div>`;
        }
      }
    }

    html += `<div class="ctx-cat">node</div>`;
    html += `<div class="ctx-item action" onclick="window.graphAddOutgoing('${nodeId}')">+ child node...</div>`;
    html += `<div class="ctx-item danger" onclick="window.graphDeleteNode('${nodeId}')">delete node</div>`;
    return html;
  }

  // ---------- position persistence ----------
  const pendingPositions = new Map();
  let saveTimer = null;
  cy.on('dragfree', 'node', (evt) => {
    const n = evt.target;
    pendingPositions.set(n.data('id'), n.position());
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(flushPositions, 400);
  });

  function flushPositions() {
    for (const [id, pos] of pendingPositions.entries()) {
      window.csrfFetch(`${api}/nodes/${encodeURIComponent(id)}`, {
        method: 'PATCH',
        body: JSON.stringify({ position_x: pos.x, position_y: pos.y }),
      }).catch(() => {});
    }
    pendingPositions.clear();
    drawMinimap();
  }

  // ---------- actions exposed globally ----------
  window.graphReload = load;

  window.graphDeleteNode = function (cyId) {
    if (!confirm('Delete this node?')) return;
    window.csrfFetch(`${api}/nodes/${encodeURIComponent(cyId)}`, { method: 'DELETE' })
      .then((r) => r.json())
      .then(() => { cy.getElementById(cyId).remove(); closeMenu(); drawMinimap(); })
      .catch(() => window.toast('Delete failed', 'danger'));
  };

  // Track currently-polling jobs so we can render a progress list.
  const activeJobs = new Map();
  const jobsPanel = document.getElementById('jobs-panel');

  function renderJobs() {
    if (!jobsPanel) return;
    if (activeJobs.size === 0) {
      jobsPanel.innerHTML = '<div class="text-dim small">no active jobs</div>';
      return;
    }
    const rows = [];
    for (const j of activeJobs.values()) {
      const pct = j.progress_total > 0 ? Math.floor((j.progress_done / j.progress_total) * 100) : 0;
      const label = j.kind === 'template' ? 'template' : j.transform_name;
      rows.push(`
        <div class="job-row">
          <div class="job-head">
            <span class="job-label">${esc(label)}</span>
            <span class="job-status job-${j.status}">${j.status}</span>
          </div>
          <div class="job-bar"><div class="job-bar-fill" style="width:${pct}%"></div></div>
          <div class="job-meta">${j.progress_done}/${j.progress_total} · +${j.total_nodes || 0} nodes${j.error ? ' · <span class="text-danger">err</span>' : ''}</div>
        </div>
      `);
    }
    jobsPanel.innerHTML = rows.join('');
  }

  function addNodesAndEdges(newNodes, newEdges) {
    const added = [];
    for (const n of newNodes || []) {
      if (cy.getElementById(n.cy_id).length === 0) {
        added.push({ group: 'nodes', data: nodeData(n), position: { x: n.position_x, y: n.position_y } });
      }
    }
    for (const e of newEdges || []) {
      if (cy.getElementById(e.cy_id).length === 0) {
        added.push({ group: 'edges', data: { id: e.cy_id, source: e.source, target: e.target, label: e.label } });
      }
    }
    if (added.length) {
      cy.add(added);
      drawMinimap();
    }
  }

  function pollJob(jobId) {
    let seenNodes = 0;
    let seenEdges = 0;
    const state = { id: jobId, status: 'queued', progress_done: 0, progress_total: 0, total_nodes: 0, kind: '', transform_name: '' };
    activeJobs.set(jobId, state);
    renderJobs();

    const tick = () => {
      window.csrfFetch(`/api/jobs/${jobId}?since_nodes=${seenNodes}&since_edges=${seenEdges}`)
        .then((r) => r.json())
        .then((data) => {
          Object.assign(state, data);
          addNodesAndEdges(data.new_nodes, data.new_edges);
          seenNodes = data.total_nodes || 0;
          seenEdges = data.total_edges || 0;
          renderJobs();

          if (data.status === 'completed' || data.status === 'failed' || data.status === 'cancelled') {
            if (data.status === 'completed') {
              window.toast(`Done: +${data.total_nodes} nodes`);
            } else {
              window.toast('Job ' + data.status + ': ' + (data.error || ''), 'danger');
            }
            setTimeout(() => { activeJobs.delete(jobId); renderJobs(); }, 4000);
            return;
          }
          setTimeout(tick, 700);
        })
        .catch((e) => {
          window.toast('Poll error: ' + e.message, 'danger');
          setTimeout(() => { activeJobs.delete(jobId); renderJobs(); }, 1500);
        });
    };
    tick();
  }

  window.runTransform = function (cyId, transformName) {
    closeMenu();
    window.toast(`Queuing ${transformName}...`);
    window.csrfFetch(`${api}/run-transform`, {
      method: 'POST',
      body: JSON.stringify({ source_cy_id: cyId, transform: transformName }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) { window.toast('Error: ' + (data.error || 'unknown'), 'danger'); return; }
        pollJob(data.job_id);
      })
      .catch((e) => window.toast('Network error: ' + e.message, 'danger'));
  };

  window.runTransformMany = function (cyIds, transformName) {
    closeMenu();
    window.toast(`Queuing ${transformName} × ${cyIds.length}...`);
    window.csrfFetch(`${api}/run-transform`, {
      method: 'POST',
      body: JSON.stringify({ source_cy_ids: cyIds, transform: transformName }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) { window.toast('Error: ' + (data.error || 'unknown'), 'danger'); return; }
        pollJob(data.job_id);
      });
  };

  window.runTemplate = function (templateId) {
    const sel = cy.$(':selected').filter('node');
    if (sel.length === 0) { window.toast('Select at least one node first', 'warn'); return; }
    const ids = sel.map((n) => n.data('id'));
    window.toast('Queuing template...');
    window.csrfFetch(`${api}/run-template`, {
      method: 'POST',
      body: JSON.stringify({ template_id: templateId, starting_cy_ids: ids }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) { window.toast('Template failed: ' + (data.error || 'unknown'), 'danger'); return; }
        pollJob(data.job_id);
      });
  };

  window.graphAddNode = function () {
    const type = prompt('Entity type (domain, email, ipv4, ...):', 'domain');
    if (!type) return;
    const value = prompt('Value:');
    if (!value) return;
    const center = cy.extent();
    const x = (center.x1 + center.x2) / 2;
    const y = (center.y1 + center.y2) / 2;
    window.csrfFetch(`${api}/nodes`, {
      method: 'POST',
      body: JSON.stringify({ entity_type: type, value, label: value, position_x: x, position_y: y }),
    })
      .then((r) => r.json())
      .then((data) => {
        cy.add({ group: 'nodes', data: nodeData(data.node), position: { x: data.node.position_x, y: data.node.position_y } });
        drawMinimap();
      });
  };

  window.graphAddOutgoing = function (fromCyId) {
    closeMenu();
    const type = prompt('Child type:', 'note');
    if (!type) return;
    const value = prompt('Value:');
    if (!value) return;
    const src = cy.getElementById(fromCyId);
    const pos = src.position();
    window.csrfFetch(`${api}/nodes`, {
      method: 'POST',
      body: JSON.stringify({ entity_type: type, value, label: value, position_x: pos.x + 220, position_y: pos.y + 40 }),
    }).then((r) => r.json()).then((n) => {
      cy.add({ group: 'nodes', data: nodeData(n.node), position: { x: n.node.position_x, y: n.node.position_y } });
      return window.csrfFetch(`${api}/edges`, {
        method: 'POST',
        body: JSON.stringify({ source: fromCyId, target: n.node.cy_id }),
      }).then((r) => r.json()).then((e) => {
        cy.add({ group: 'edges', data: { id: e.edge.cy_id, source: e.edge.source, target: e.edge.target } });
        drawMinimap();
      });
    });
  };

  // ---------- template mode ----------
  function compatibleNextSteps(fromNode) {
    const type = fromNode.data('entity_type');
    // template:input accepts anything downstream
    if (type === 'template:input') return cfg.transforms || [];
    if (type !== 'template:transform') return [];
    const fromName = fromNode.data('value');
    const fromSpec = (cfg.transforms || []).find((t) => t.name === fromName);
    if (!fromSpec) return cfg.transforms || [];
    const outs = fromSpec.output_types || [];
    return (cfg.transforms || []).filter((t) => {
      const ins = t.input_types || [];
      if (ins.includes('*')) return true;
      return ins.some((it) => outs.includes(it));
    });
  }

  function addNodeThenMaybeEdge(payload, parentCyId, onAdded) {
    return window.csrfFetch(`${api}/nodes`, { method: 'POST', body: JSON.stringify(payload) })
      .then((r) => r.json())
      .then((n) => {
        cy.add({ group: 'nodes', data: nodeData(n.node), position: { x: n.node.position_x, y: n.node.position_y } });
        if (!parentCyId) { drawMinimap(); onAdded && onAdded(n.node); return; }
        return window.csrfFetch(`${api}/edges`, {
          method: 'POST',
          body: JSON.stringify({ source: parentCyId, target: n.node.cy_id }),
        }).then((r) => r.json()).then((e) => {
          cy.add({ group: 'edges', data: { id: e.edge.cy_id, source: e.edge.source, target: e.edge.target } });
          drawMinimap();
          onAdded && onAdded(n.node);
        });
      });
  }

  if (isTemplate) {
    let edgeFrom = null;
    cy.on('tap', 'node', (evt) => {
      if (!evt.originalEvent.shiftKey) return;
      if (!edgeFrom) {
        edgeFrom = evt.target.data('id');
        window.toast('Shift-click target to connect');
      } else {
        const to = evt.target.data('id');
        if (to !== edgeFrom) {
          window.csrfFetch(`${api}/edges`, {
            method: 'POST',
            body: JSON.stringify({ source: edgeFrom, target: to }),
          }).then((r) => r.json()).then((e) => {
            cy.add({ group: 'edges', data: { id: e.edge.cy_id, source: e.edge.source, target: e.edge.target } });
            window.toast('Edge created');
          });
        }
        edgeFrom = null;
      }
    });

    window.templateAddInput = function () {
      const value = prompt('Input slot label:', 'input');
      if (!value) return;
      const center = cy.extent();
      addNodeThenMaybeEdge({
        entity_type: 'template:input',
        value, label: value,
        position_x: center.x1 + 80, position_y: (center.y1 + center.y2) / 2,
      }, null, (newNode) => {
        cy.$('node:selected').unselect();
        cy.getElementById(newNode.cy_id).select();
        window.toast('Input slot added — click a transform to chain');
      });
    };

    function transformPosition(parentNode) {
      if (parentNode) {
        const p = parentNode.position();
        // Spread children vertically based on existing out-degree so we don't stack.
        const outDeg = parentNode.outgoers('edge').length;
        return { x: p.x + 260, y: p.y + (outDeg - 0) * 90 - 40 };
      }
      const ext = cy.extent();
      return { x: (ext.x1 + ext.x2) / 2, y: (ext.y1 + ext.y2) / 2 };
    }

    function dropTransformAfter(parentCyId, transformName) {
      const t = (cfg.transforms || []).find((x) => x.name === transformName);
      if (!t) return;
      const parent = parentCyId ? cy.getElementById(parentCyId) : null;
      const parentNode = parent && parent.length ? parent : null;
      const pos = transformPosition(parentNode);
      addNodeThenMaybeEdge({
        entity_type: 'template:transform',
        value: transformName,
        label: t.display_name || transformName,
        data: { transform_name: transformName },
        position_x: pos.x, position_y: pos.y,
      }, parentNode ? parentNode.data('id') : null, (newNode) => {
        // Select the freshly added step so the NEXT click in the sidebar chains onto it.
        cy.$('node:selected').unselect();
        cy.getElementById(newNode.cy_id).select();
        window.toast(parentNode ? 'Step chained' : 'Step added');
      });
    }

    // Sidebar click: chain onto the currently selected node if there is one.
    window.templateAddTransform = function (transformName) {
      const sel = cy.$('node:selected');
      const parentCyId = sel.length === 1 ? sel[0].data('id') : null;
      dropTransformAfter(parentCyId, transformName);
    };

    // Context-menu click: explicit parent passed in.
    window.templateAddNextStep = function (parentCyId, transformName) {
      closeMenu();
      dropTransformAfter(parentCyId, transformName);
    };
  }

  // ---------- minimap (custom, draggable viewport) ----------
  const miniCanvas = document.getElementById('mini-canvas');
  const miniRect = document.getElementById('mini-viewport');

  function drawMinimap() {
    if (!miniCanvas) return;
    const ctx = miniCanvas.getContext('2d');
    const w = miniCanvas.width = miniCanvas.offsetWidth * devicePixelRatio;
    const h = miniCanvas.height = miniCanvas.offsetHeight * devicePixelRatio;
    miniCanvas.style.width = miniCanvas.offsetWidth + 'px';
    miniCanvas.style.height = miniCanvas.offsetHeight + 'px';
    ctx.clearRect(0, 0, w, h);

    const nodes = cy.nodes();
    if (nodes.length === 0) { drawViewport(); return; }
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    nodes.forEach((n) => {
      const p = n.position();
      if (p.x < minX) minX = p.x; if (p.y < minY) minY = p.y;
      if (p.x > maxX) maxX = p.x; if (p.y > maxY) maxY = p.y;
    });
    const padding = 60;
    minX -= padding; minY -= padding; maxX += padding; maxY += padding;
    const gw = maxX - minX, gh = maxY - minY;
    const scale = Math.min(w / gw, h / gh);
    const ox = (w - gw * scale) / 2 - minX * scale;
    const oy = (h - gh * scale) / 2 - minY * scale;

    // edges
    ctx.strokeStyle = 'rgba(60,82,66,0.8)';
    ctx.lineWidth = 1 * devicePixelRatio;
    cy.edges().forEach((e) => {
      const s = e.source().position();
      const t = e.target().position();
      ctx.beginPath();
      ctx.moveTo(s.x * scale + ox, s.y * scale + oy);
      ctx.lineTo(t.x * scale + ox, t.y * scale + oy);
      ctx.stroke();
    });
    // nodes
    nodes.forEach((n) => {
      const p = n.position();
      const s = styleFor(n.data('entity_type'));
      ctx.fillStyle = s.color;
      const px = p.x * scale + ox;
      const py = p.y * scale + oy;
      ctx.fillRect(px - 2, py - 2, 4 * devicePixelRatio, 4 * devicePixelRatio);
    });

    miniMap = { minX, minY, maxX, maxY, scale, ox, oy, w, h };
    drawViewport();
  }

  let miniMap = null;

  function drawViewport() {
    if (!miniRect || !miniMap) return;
    const ext = cy.extent();
    const { scale, ox, oy } = miniMap;
    const x = ext.x1 * scale + ox;
    const y = ext.y1 * scale + oy;
    const ww = (ext.x2 - ext.x1) * scale;
    const hh = (ext.y2 - ext.y1) * scale;
    const bounds = miniCanvas.getBoundingClientRect();
    const canvasW = miniCanvas.width / devicePixelRatio;
    const canvasH = miniCanvas.height / devicePixelRatio;
    miniRect.style.left = (x / devicePixelRatio) + 'px';
    miniRect.style.top = (y / devicePixelRatio) + 'px';
    miniRect.style.width = (ww / devicePixelRatio) + 'px';
    miniRect.style.height = (hh / devicePixelRatio) + 'px';
  }

  cy.on('viewport', drawViewport);

  // Minimap: click + drag to move the viewport.
  if (miniCanvas) {
    let dragging = false;

    function panToModel(gx, gy) {
      // Place model coordinate (gx, gy) at the visible center of the canvas.
      const z = cy.zoom();
      cy.pan({
        x: cy.width() / 2 - gx * z,
        y: cy.height() / 2 - gy * z,
      });
    }

    function panFromMiniXY(clientX, clientY) {
      if (!miniMap) return;
      const rect = miniCanvas.getBoundingClientRect();
      // model coords under the cursor in the minimap
      const mx = (clientX - rect.left) * devicePixelRatio;
      const my = (clientY - rect.top) * devicePixelRatio;
      const gx = (mx - miniMap.ox) / miniMap.scale;
      const gy = (my - miniMap.oy) / miniMap.scale;
      panToModel(gx, gy);
    }

    const startDrag = (e) => {
      dragging = true;
      e.preventDefault();
      panFromMiniXY(e.clientX, e.clientY);
    };
    miniCanvas.addEventListener('mousedown', startDrag);
    if (miniRect) miniRect.addEventListener('mousedown', startDrag);
    window.addEventListener('mousemove', (e) => { if (dragging) panFromMiniXY(e.clientX, e.clientY); });
    window.addEventListener('mouseup', () => { dragging = false; });
  }

  window.graphFit = function () { cy.fit(undefined, 40); drawMinimap(); };
  window.graphLayout = function () {
    cy.layout({ name: 'cose', animate: true, animationDuration: 400, nodeRepulsion: 40000, idealEdgeLength: 120 }).run();
    setTimeout(drawMinimap, 500);
  };

  // ---------- transforms palette filter ----------
  const filterInput = document.getElementById('transform-filter');
  if (filterInput) {
    const groups = Array.from(document.querySelectorAll('.transform-group'));
    const items = Array.from(document.querySelectorAll('.transform-item'));

    function applyFilter(q) {
      q = (q || '').trim().toLowerCase();
      if (!q) {
        items.forEach((i) => i.classList.remove('filtered-out'));
        groups.forEach((g) => g.classList.remove('filtered-out'));
        return;
      }
      const terms = q.split(/\s+/);
      groups.forEach((g) => {
        const groupItems = Array.from(g.querySelectorAll('.transform-item'));
        let visible = 0;
        groupItems.forEach((it) => {
          const hay = it.dataset.search || '';
          const ok = terms.every((t) => hay.includes(t));
          it.classList.toggle('filtered-out', !ok);
          if (ok) visible++;
        });
        g.classList.toggle('filtered-out', visible === 0);
        if (visible > 0) g.open = true;
      });
    }

    filterInput.addEventListener('input', (e) => applyFilter(e.target.value));
    filterInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { filterInput.value = ''; applyFilter(''); filterInput.blur(); }
    });

    // Ctrl+K / Cmd+K focuses the filter
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        filterInput.focus();
        filterInput.select();
      }
    });
  }

  // ---------- global keyboard shortcuts ----------
  document.addEventListener('keydown', (e) => {
    // ignore when typing in an input/textarea/contenteditable
    const tag = (e.target && e.target.tagName) || '';
    const typing = tag === 'INPUT' || tag === 'TEXTAREA' || (e.target && e.target.isContentEditable);

    if (e.key === 'Escape') {
      closeMenu();
      if (presentMode) setPresentMode(false);
      return;
    }
    if (!typing && (e.key === 'p' || e.key === 'P')) {
      if (!isTemplate) {
        e.preventDefault();
        window.togglePresentMode();
      }
    }
  });

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  load();
})();
