"""Geolocate an IP address via ip-api.com (no key required, 45 req/min)."""

import json
import urllib.request

from osint_engine.sdk import Node, transform


@transform(
    name="ip.geo",
    display_name="IP geolocation",
    description="Queries http://ip-api.com/json/{ip} (free, no key, ~45 req/min) and emits country, city, ISP, ASN.",
    category="network",
    input_types=["ipv4", "ipv6"],
    output_types=["location", "organization", "asn", "note"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    url = f"http://ip-api.com/json/{node.value}?fields=status,message,country,countryCode,regionName,city,lat,lon,isp,org,as"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode("utf-8", errors="replace"))
    except Exception as exc:
        return [Node(type="note", value=f"geo error: {exc}", label="geo error")]

    if data.get("status") != "success":
        return [Node(type="note", value=data.get("message", "lookup failed"), label="geo failed")]

    out: list[Node] = []
    country = data.get("country")
    city = data.get("city")
    region = data.get("regionName")
    if country:
        loc_value = ", ".join(filter(None, [city, region, country]))
        out.append(Node(
            type="location",
            value=loc_value,
            label=loc_value,
            data={
                "country": country, "countryCode": data.get("countryCode"),
                "region": region, "city": city,
                "lat": data.get("lat"), "lon": data.get("lon"),
            },
        ))
    if data.get("isp"):
        out.append(Node(type="organization", value=data["isp"], label=f"ISP: {data['isp']}"))
    if data.get("org") and data.get("org") != data.get("isp"):
        out.append(Node(type="organization", value=data["org"], label=f"Org: {data['org']}"))
    if data.get("as"):
        out.append(Node(type="asn", value=data["as"], label=data["as"]))
    return out
