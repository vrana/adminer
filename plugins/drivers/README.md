Using drivers: https://www.adminer.org/plugins/#use

Developing drivers: https://www.adminer.org/en/drivers/

The type declarations must be compatible both with source codes and the compiled version (where PHP5-incompatible types are stripped). It means:
- specify return type if parent specifies it
- do not specify scalar parameter type
