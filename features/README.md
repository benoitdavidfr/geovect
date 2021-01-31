# Serveur conforme au standard OGC API Features

Le code de ce module implémente un serveur respectant le standard OGC API Features (ISO 19168-1:2020).  
3 types de serveur sont prévus en fonction de la source des données:

  - données stockées dans une base MySql ou un schéma PgSql/PostGis
  - données exposées par un serveur WFS
  - fichiers JSON stockées dans un répertoire
  
Seul le le premier type a été affiné et passe
les [tests CITE](https://cite.opengeospatial.org/teamengine/about/ogcapi-features-1.0/1.0/site/).
