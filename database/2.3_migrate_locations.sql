 
INSERT INTO ospos_item_quantities (item_id, location_id, quantity) (SELECT item_id, location_id, quantity FROM ospos_items oi, ospos_stock_locations osl where oi.location = osl.location_name);
ALTER TABLE ospos_items DROP COLUMN location;
