# 06. Acciones de impresión

Cuando otro plugin usa DirectPrint para imprimir (por ejemplo, imprimir el albarán al cerrar una preparación de pedidos), no elige una impresora concreta en su código. En su lugar declara una **acción de impresión**: una clave con nombre, como `preparacion-albaran`, que representa "este punto imprime esto". Tú decides desde DirectPrint a qué impresora va cada acción.

De esta forma la configuración de impresoras vive en un único sitio (DirectPrint) y no se reparte por cada plugin. Si mañana cambias de impresora, lo ajustas aquí y todos los plugins que usen esa acción lo respetan, sin tocar nada más.

## Dónde se ven las acciones

Las acciones aparecen en **Admin → Impresoras**, en la pestaña **Acciones de impresión**. Cada plugin que sabe imprimir da de alta sus acciones automáticamente al instalarse o actualizarse, así que no tienes que crearlas a mano (por eso esta pestaña no tiene botón de "Nuevo").

Cada fila muestra la acción (su clave), una descripción legible y la impresora asignada.

## Asignar una impresora a una acción

Haz clic en una acción para abrir su ficha y elige la impresora en el desplegable **Impresora**. Guarda y listo: a partir de ese momento, cada vez que un plugin imprima usando esa acción, el trabajo saldrá por esa impresora.

## Qué pasa si no asignas impresora

Si dejas la impresora vacía, la acción usa la **impresora predeterminada** (la que tenga marcada la casilla "Predeterminada" en su ficha). Así, con solo tener una impresora marcada como predeterminada, todas las acciones funcionan aunque no las hayas configurado una a una.

Si no hay ninguna impresora predeterminada y la acción no tiene impresora asignada, el trabajo se registra en el historial en estado Error indicando que no se encontró impresora; nada más se rompe.

## Si borras una impresora asignada

Si eliminas una impresora que estaba asignada a una o varias acciones, esas acciones quedan simplemente sin impresora (vuelven a usar la predeterminada). No se borran las acciones ni se pierde su configuración.
