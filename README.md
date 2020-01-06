# Stream wrapper issue 7.4
Managed to boil it down to this code

```bash
/usr/bin/php7.3 run.php
```

is fine, but

```bash
/usr/bin/php7.4 run.php
```

results in 
```bash
PHP Parse error:  syntax error, unexpected end of file in IncludeFile.php on line 7
```