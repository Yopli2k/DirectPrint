# Changelog

All significant changes to this project will be documented in this file.

## [1.0] - 2026-07-21

### New Features and Improvements

- First version of the plugin.
- Direct printing to CUPS: send PDF or plain text files to a printer without opening the PDF in the browser.
- Printer administration screen (Admin → Printers): visible name, CUPS queue, default paper size and orientation, number of copies, active and default flags, and notes.
- "Check queue" and "Print test page" actions to validate that a printer is correctly configured.
- Reusable `PrinterService` so other plugins can print a file, binary contents or plain text with a simple API, without depending on controllers or views.
- Printing of a sales or purchase document (invoice, delivery note, order or estimate): either passing the already loaded instance (`printDocument`) or by model name and code (`printDocumentById`), generating its PDF automatically.
- Print actions (action → printer mapping): other plugins register named actions and print by action (`printForAction`, `printerIdForAction`); the admin assigns the printer of each action in Admin → Printers → Print actions, falling back to the default printer.
- Print jobs history with filters by date, printer, status and user. The "Sent to CUPS" status means the job was accepted by CUPS, not that it was physically printed.
- Automatic cleanup of leftover temporary files through a cron task.
- Security by design: commands are executed without a shell, print options are limited to a whitelist (copies, paper size, orientation), files must live inside a private controlled folder, and only allowed file types and sizes are accepted.

### Bug fixes
