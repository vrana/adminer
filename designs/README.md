Copy `adminer.css` alongside Adminer PHP script to use an alternative design.
A design for the dark mode only should be named `adminer-dark.css`, a design supporting both color schemes can handle the dark mode in `adminer.css` in `@media (prefers-color-scheme: dark)`.

Each design starts with a `/* Adminer design <name> */` comment matching its directory name.
Adminer uses it together with a checksum to mark a used design not matching the current Adminer version.

Gallery of designs (including external): https://www.adminer.org/#extras

See also: [plugins/designs.php](/plugins/designs.php)
