all: ne_110m/admin_0_boundary_lines_land.geojson ne_110m/admin_0_countries.geojson ne_110m/coastline.geojson \
  ne_10m/admin_0_boundary_lines_land.geojson ne_10m/admin_0_countries.geojson ne_10m/coastline.geojson ne_10m/admin_0_map_units.geojson
ne_110m/admin_0_boundary_lines_land.geojson : ne110m.php
	php ne110m.php admin_0_boundary_lines_land > ne_110m/admin_0_boundary_lines_land.geojson
ne_110m/admin_0_countries.geojson : ne110m.php
	php ne110m.php admin_0_countries > ne_110m/admin_0_countries.geojson
ne_110m/coastline.geojson : ne110m.php
	php ne110m.php coastline > ne_110m/coastline.geojson
ne_10m/admin_0_boundary_lines_land.geojson : ne10m.php
	php ne10m.php admin_0_boundary_lines_land > ne_10m/admin_0_boundary_lines_land.geojson
ne_10m/admin_0_countries.geojson : ne10m.php
	php ne10m.php admin_0_countries > ne_10m/admin_0_countries.geojson
ne_10m/admin_0_map_units.geojson : ne10m.php
	php ne10m.php admin_0_map_units > ne_10m/admin_0_map_units.geojson
ne_10m/coastline.geojson : ne10m.php
	php ne10m.php coastline > ne_10m/coastline.geojson
