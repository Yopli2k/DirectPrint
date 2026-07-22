# 02. Administración de impresoras

## Antes de empezar: comprobar CUPS en el servidor

Antes de dar de alta una impresora en FacturaScripts, conviene comprobar que el servidor puede imprimir por su cuenta. Conectado por SSH al servidor, como el usuario con el que corre PHP (normalmente `www-data`):

```bash
# lista las colas de impresión disponibles
sudo -u www-data lpstat -a

# envía una página de prueba a una cola concreta
sudo -u www-data lp -d NOMBRE_DE_LA_COLA /usr/share/cups/data/testprint
```

Si estos dos comandos funcionan, DirectPrint podrá imprimir con esa misma cola.

## Acceder a la pantalla de impresoras

En el menú **Admin → Impresoras** se accede a la pantalla de administración, que tiene dos pestañas: **Impresoras** y **Historial**. Este documento cubre la primera.

## Dar de alta una impresora

Cada impresora se define con estos campos:

- **Nombre**: nombre visible en FacturaScripts para identificar la impresora (por ejemplo, "Impresora oficina" o "Etiquetadora almacén").
- **Cola CUPS**: nombre exacto de la cola tal y como aparece en `lpstat -a`. **No** es una IP ni una ruta de red: es el nombre de la cola ya configurada en CUPS.
- **Tamaño de papel**: A4, A5, Letter o Legal. Se usa como valor por defecto al imprimir con esta impresora.
- **Orientación**: Vertical (por defecto) u Horizontal.
- **Copias**: número de copias por defecto (mínimo 1).
- **Activa**: si está desmarcada, la impresora no se puede usar para imprimir (ni desde la pantalla ni desde otros plugins), aunque se conserva en la lista y en el historial.
- **Predeterminada**: marca esta impresora como la que se usa cuando otro plugin pide imprimir sin indicar una impresora concreta. Solo puede haber una impresora predeterminada: al marcar una, se desmarca automáticamente cualquier otra que lo estuviera.
- **Observaciones**: campo de texto libre para anotaciones internas.

El nombre de la impresora debe ser único: no se pueden dar de alta dos impresoras con el mismo nombre.

Al escribir en el campo **Cola CUPS**, FacturaScripts sugiere automáticamente las colas detectadas en el servidor (si `lpstat` es accesible). Si no aparece ninguna sugerencia, se puede escribir el nombre a mano; eso no impide guardar la impresora, aunque conviene comprobarlo con el botón **Comprobar cola** antes de darla por buena (ver [03. Comprobar cola e imprimir prueba](03-comprobar-cola-e-imprimir-prueba.md)).

## Impresora inactiva

Una impresora marcada como inactiva no se puede usar para imprimir, aunque siga apareciendo en el listado y en el historial de trabajos anteriores. Es útil para dar de baja temporalmente una impresora en mantenimiento sin perder su configuración ni su histórico.
