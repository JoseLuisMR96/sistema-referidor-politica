"""Simplifica los GeoJSON de municipios (Douglas-Peucker) para el mapa web.

Uso: python -I simplificar.py <carpeta_origen> <carpeta_destino> [tolerancia_grados]
"""
import glob
import json
import os
import sys

origen, destino = sys.argv[1], sys.argv[2]
tol = float(sys.argv[3]) if len(sys.argv) > 3 else 0.0005  # ~55 m
os.makedirs(destino, exist_ok=True)


def dp(puntos, tol):
    n = len(puntos)
    if n < 3:
        return puntos
    guardar = [False] * n
    guardar[0] = guardar[-1] = True
    pila = [(0, n - 1)]
    tol2 = tol * tol
    while pila:
        a, b = pila.pop()
        ax, ay = puntos[a][0], puntos[a][1]
        bx, by = puntos[b][0], puntos[b][1]
        dx, dy = bx - ax, by - ay
        largo2 = dx * dx + dy * dy
        max_d, idx = -1.0, -1
        for i in range(a + 1, b):
            px, py = puntos[i][0], puntos[i][1]
            if largo2 == 0:
                d = (px - ax) ** 2 + (py - ay) ** 2
            else:
                t = ((px - ax) * dx + (py - ay) * dy) / largo2
                t = max(0.0, min(1.0, t))
                qx, qy = ax + t * dx, ay + t * dy
                d = (px - qx) ** 2 + (py - qy) ** 2
            if d > max_d:
                max_d, idx = d, i
        if max_d > tol2:
            guardar[idx] = True
            pila.append((a, idx))
            pila.append((idx, b))
    return [p for p, g in zip(puntos, guardar) if g]


def anillo(r):
    s = dp(r, tol)
    if len(s) < 4:  # un anillo necesita al menos 4 puntos (cerrado)
        s = r if len(r) < 4 else [r[0], r[len(r) // 3], r[2 * len(r) // 3], r[0]]
    return [[round(x, 5), round(y, 5)] for x, y, *_ in s]


def geometria(g):
    if g['type'] == 'Polygon':
        return {'type': 'Polygon', 'coordinates': [anillo(r) for r in g['coordinates']]}
    if g['type'] == 'MultiPolygon':
        return {'type': 'MultiPolygon', 'coordinates': [[anillo(r) for r in poly] for poly in g['coordinates']]}
    return g


total_antes = total_despues = 0
for f in sorted(glob.glob(os.path.join(origen, '*.geojson'))):
    geo = json.load(open(f, encoding='utf-8'))
    feats = []
    for ft in geo['features']:
        p = ft['properties']
        feats.append({
            'type': 'Feature',
            'properties': {k: p.get(k) for k in ('COD_MPIO', 'NOM_MPIO', 'NOM_DPTO')},
            'geometry': geometria(ft['geometry']),
        })
    salida = os.path.join(destino, os.path.basename(f))
    with open(salida, 'w', encoding='utf-8') as o:
        json.dump({'type': 'FeatureCollection', 'features': feats}, o, ensure_ascii=False, separators=(',', ':'))
    a, d = os.path.getsize(f), os.path.getsize(salida)
    total_antes += a
    total_despues += d
    print(f'{os.path.basename(f):28s} {a / 1e6:7.1f} MB -> {d / 1e3:7.0f} KB')
print(f'TOTAL {total_antes / 1e6:.0f} MB -> {total_despues / 1e6:.1f} MB')
