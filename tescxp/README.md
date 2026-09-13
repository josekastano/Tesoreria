# Tesorería — estructura del proyecto

```
Tesoreria/
├── *.php                  ← páginas del módulo (raíz)
├── css/                   ← todas las hojas de estilo (14)
├── js/                    ← todos los scripts (11)
├── img/                   ← imágenes (1)
├── previews/              ← previsualizaciones HTML (11) — no van a producción
├── _ds/                   ← design system "Modernist" (paquete aparte, intacto)
└── .thumbnail             ← miniatura WebP del design system
```

## Raíz (10 archivos PHP)

| Archivo                  | CSS                        | JS                        |
|--------------------------|----------------------------|---------------------------|
| `archivo_plano.php`      | `css/archivo_plano.css`    | `js/archivo_plano.js`     |
| `bancos.php`             | `css/bancos.css`           | `js/bancos.js`            |
| `caja_menor.php`         | `css/caja_menor.css`       | `js/caja_menor.js`        |
| `cronograma.php`         | `css/cronograma.css`       | `js/cronograma.js`        |
| `ctas_empresa.php`       | `css/ctas_empresa.css`     | `js/ctas_empresa.js`      |
| `ctas_proveedores.php`   | `css/ctas_proveedores.css` | `js/ctas_proveedores.js`  |
| `cuentasxpagar.php`      | `css/cuentasxpagar.css`    | `js/cuentasxpagar.js`     |
| `festivos.php`           | `css/festivos.css`         | `js/festivos.js`          |
| `parametros.php`         | `css/parametros.css`       | `js/parametros.js`        |
| `proveedores.php`        | `css/proveedores.css`      | `js/proveedores.js`       |

## Rutas

No se modificó ninguna ruta dentro de los PHP. Cada página ya declaraba
sus recursos así:

```php
$page_extra_css = ["../modules/tescxp/css/archivo_plano.css"];
$page_extra_js  = ["../modules/tescxp/js/archivo_plano.js"];
```

Esas rutas asumen que la carpeta del módulo contiene `css/` y `js/`, que
es exactamente la estructura que ahora tiene el proyecto. Al copiar esta
carpeta a `modules/tescxp/` todo resuelve sin tocar código.

Excepción: `proveedores.php` apunta a `../modules/compro/...`, no a
`tescxp`. Pertenece al módulo de compras.

## CSS sin página asociada

- `css/historial_pagos.css`  → sí tiene preview, falta el `.php` y el `.js`
- `css/motivos_rechazo.css`  → sí tiene preview, falta el `.php` y el `.js`
- `css/pmtros_compras.css`   → sin preview ni PHP
- `css/registro_pagos.css`   → sin preview ni PHP

## previews/

HTML estáticos generados aparte, con su runtime `js/support.js`. Se les
quitó el sufijo `.dc` del nombre y se les reapuntaron las rutas a
`../css/` y `../js/`. No hacen falta en producción: si no los usas, borra
`previews/` y `js/support.js`.

## _ds/

Paquete autocontenido del design system "Modernist": `styles.css`,
`_ds_bundle.js`, `_ds_manifest.json`, `readme.md` y `_adherence.oxlintrc.json`.
Se dejó intacto a propósito — su readme y su manifest referencian rutas
relativas dentro de la propia carpeta, así que repartir su CSS y su JS en
`css/` y `js/` lo rompería. Ningún archivo del proyecto lo referencia
todavía.
