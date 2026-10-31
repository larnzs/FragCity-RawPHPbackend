-- Apply once to GLOBAL_simpleadmin after SimpleAdmin migrations 013 and 015.
-- Only the name, SteamID and last-used timestamp are queried; the IP column is not read.
-- Check first with: SHOW INDEX FROM sa_players_ips;
ALTER TABLE sa_players_ips
    ADD INDEX idx_sa_players_ips_name_steamid_used_at (name, steamid, used_at);
