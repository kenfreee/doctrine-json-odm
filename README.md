#### Original README
[Readme.md](https://github.com/dunglas/doctrine-json-odm/blob/master/README.md)

#### Changes
* Converted config services.xml => services.yml
* Added compiler pass for the embedded serializer, which injects normalizers from the native serializer. 
* Changed logic of `ObjectNormalizer.php`

PHP ^7.1 + Symfony 4.*