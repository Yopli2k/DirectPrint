# 05. Preguntas frecuentes

## He guardado la impresora pero "Comprobar cola" dice que no existe

El nombre escrito en el campo Cola CUPS no coincide exactamente con ninguna cola
detectada por `lpstat -a` en el servidor. Revisa mayúsculas/minúsculas y espacios, y
confirma el nombre real ejecutando `lpstat -a` directamente en el servidor. También
puede deberse a que el usuario con el que corre PHP no tenga permiso para consultar
las colas (ver la siguiente pregunta).

## "Comprobar cola" o "Imprimir prueba" no funcionan y no dan pistas claras

Lo más habitual es un problema de permisos: el usuario con el que corre PHP
(normalmente `www-data`) no puede ejecutar `lp` o `lpstat`. Compruébalo directamente
en el servidor:

```bash
sudo -u www-data lpstat -a
sudo -u www-data lp -d NOMBRE_DE_LA_COLA /usr/share/cups/data/testprint
```

Si estos comandos fallan con ese usuario (aunque funcionen con tu propio usuario),
el problema está en la configuración de CUPS o de permisos del sistema, no en
FacturaScripts.

## El trabajo aparece como "Enviado" pero no ha salido nada por la impresora

"Enviado" significa que **CUPS ha aceptado** el trabajo en su cola, no que se haya
impreso físicamente. Revisa que la impresora esté encendida, con papel y sin errores
propios (atasco, tóner, etc.), y consulta la cola directamente en el servidor:

```bash
lpstat -o NOMBRE_DE_LA_COLA
```

DirectPrint no recibe confirmación de CUPS cuando un trabajo termina de imprimirse
físicamente, así que ese seguimiento final hay que hacerlo desde el propio CUPS o
desde la impresora.

## Un trabajo aparece como "Error", ¿dónde veo el motivo?

Abre el detalle del trabajo en el historial (ver
[04. Historial de trabajos](04-historial-de-trabajos.md)): el campo Error contiene el
motivo. Los más habituales son impresora inactiva, cola no válida, o un archivo que no
cumple las restricciones de tipo o tamaño permitidas.

## ¿Se puede imprimir un fichero de cualquier tamaño o tipo?

No. Por seguridad, solo se admiten ficheros PDF y de texto plano, con un tamaño máximo
de 20 MB. Un fichero que no cumpla estas condiciones se rechaza antes de intentar
enviarlo a CUPS. Ver
[52. Seguridad y validaciones](52-seguridad-y-validaciones.md) para el detalle
técnico.

## ¿Puedo imprimir desde una impresora local (USB) de mi ordenador?

No directamente. DirectPrint imprime en impresoras dadas de alta como colas CUPS **en
el servidor**, no en el equipo del usuario. Si necesitas usar una impresora local,
tendría que estar compartida y accesible desde el propio servidor como una cola CUPS
más.

## No veo sugerencias de colas al escribir en el campo Cola CUPS

Es normal si el servidor no permite que PHP consulte `lpstat`. El campo sigue
funcionando como texto libre: escribe el nombre exacto de la cola y valídalo después
con el botón **Comprobar cola**.
