# Changelog

Todos los cambios notables en este proyecto se documentarán en este archivo.

## [1.0] - 2026-07-21

### Nuevas funcionalidades y mejoras

- Primera versión del plugin.
- Impresión directa mediante CUPS: envía ficheros PDF o de texto a una impresora sin abrir el PDF en el navegador.
- Pantalla de administración de impresoras (Admin → Impresoras): nombre visible, cola CUPS, tamaño de papel y orientación por defecto, número de copias, marcas de activa y por defecto, y observaciones.
- Acciones "Comprobar cola" e "Imprimir prueba" para validar que una impresora está bien configurada.
- Servicio reutilizable `PrinterService` para que otros plugins impriman un fichero, un contenido binario o texto plano con una API sencilla, sin depender de controladores ni vistas.
- Impresión de un documento de compra o venta (factura, albarán, pedido o presupuesto): pasando la instancia ya cargada (`printDocument`) o por nombre de modelo y código (`printDocumentById`), generando su PDF automáticamente.
- Historial de trabajos de impresión con filtros por fecha, impresora, estado y usuario. El estado "Enviado a CUPS" indica que el trabajo fue aceptado por CUPS, no que se haya impreso físicamente.
- Limpieza automática de los ficheros temporales sobrantes mediante una tarea cron.
- Seguridad por diseño: los comandos se ejecutan sin shell, las opciones de impresión se limitan a una lista blanca (copias, tamaño de papel, orientación), los ficheros deben estar dentro de una carpeta privada controlada y solo se aceptan tipos y tamaños permitidos.

### Correcciones de errores
