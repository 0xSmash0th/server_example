
echo -- > merged.sql

#python gen_asset_buildings.py >> merged.sql
# as of 07.11, table building was removed

python gen_asset_users.py >> merged.sql

python gen_asset_regions.py >> merged.sql

