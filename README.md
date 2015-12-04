# Frete.co Correios

### Extensão de integração para Magento com suporte aos Correios

## Recursos
* Troca o uso do webservice dos correios pelo webservice do Frete.co a partir do modulo de correios do Pedro Teixeira.

## Instalação
### Testado no 1.7.0.2 (mas deve funcionar em outras versões)

### Instalar com [modgit](https://github.com/jreinke/modgit)
    $ cd path/to/magento
    $ modgit init
    $ modgit add freteco-correios git@github.com:frete/magento-correios.git

Ou faça manualmente:

* Faça download da última versão [aqui](https://github.com/frete/magento-correios/downloads)
* Descompacte na raíz da instalação da loja *mesclando com as pastas existentes*
* Limpe o cache

## Dependência
* Depende do modulo de correios do [Pedro Teixeira](http://www.pteixeira.com.br/modulo-de-frete-para-magento-com-tracking-versao-4-2/) estar instalado

### TODO
* Validar a URL correta do Frete.co no config.xml
* Testar/implementar retornos no Ws do Frete.co

## Autor
[Ricardo Martins](http://ricardomartins.info/)  (<ricardo.martins@e-smart.com.br>)
