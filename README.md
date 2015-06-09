# phpForkRun
Execute multiple things in parallel by using fork-join model.

### WARNING!
Do **NOT** use this class in webservers. Forking (especially under threaded servers)
is a big no-no. This is intended to be used in command-line applications. Also in case of open files, database connections etc, do **NOT** forget to reopen all these resources in child and parent alike. This class does provides callback to hook into when this happens.

### Intended usage
For processing smaller data sets, where processing each element take considerable
amount of time (networking requests, computation...).

See examples.

### Licence
WTFPL
