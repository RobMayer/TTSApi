<?php

namespace handlers {
    class MiniController {
        public function build() {
            return "--ThatRobHuman MiniHUDController
TRH_Class = 'mini.controller'
local TRH_Version = '".\UTILITY_VERSIONS['mini.controller'][0]['version']."'
local TRH_Version_Next = '???'
local TRH_Version_Changes = {}
local TRH_Meta = '".\lib\VersionManager::token('mini.controller')."'
local const = { SPECTATOR = 1, PLAYER = 2, PROMOTED = 4, BLACK = 8, HOST = 16, ALL = 31, NOSPECTATOR = 30, OFF = 0, INCREMENTAL = 1, STATIC = 2, BRACKETS = 3, SIMPLEGAUGE = 1, RADIUS = 2, COMPLEXGAUGE = 3, DEFINED = 4, PLASTIC = 0, WOOD = 1, METAL = 2, CARDBOARD = 3, UNKNOWN = 0, UPDATENEEDED = 1, UPTODATE = 2, BADCONNECT = 3}

config = {
    ACCESS = {const.BLACK, const.HOST}, --list who can interact with this thing
    TURNS = true --use the turn-tracking feature
}

local metaconfig = {
    UPDATECHECK = true,
    AUTOUPDATE = false,
}

local needsUpdate = const.UNKNOWN;
local mapG2I = {}
local mapI2G = {}
local assetBuffer = {}
local turn = 1

local ui_mode = '0'

function ui_setmode(player, value, id)
    ui_mode = value
    rebuildUI()
end

local sanitize = function(str)
    return str:gsub('[<>]', '')
end
local striptags = function(str)
    str = sanitize(str)
    str = str:gsub('%[/?[iI]%]', '')
    str = str:gsub('%[/?[bB]%]', '')
    str = str:gsub('%[/?[uU]%]', '')
    str = str:gsub('%[/?[sS]%]', '')
    str = str:gsub('%[/?[sS][uU][bB]%]', '')
    str = str:gsub('%[/?[sS][uU][pP]%]', '')
    str = str:gsub('%[/?[sS][uU][pP]%]', '')
    str = str:gsub('%[/?%-%]', '')
    str = str:gsub('%[/?[a-fA-F0-9][a-fA-F0-9][a-fA-F0-9][a-fA-F0-9][a-fA-F0-9][a-fA-F0-9]%]', '')
    return str
end

local permit = function(player)
    local rights = bit32.bor(
    	(player.host and const.HOST or 0),
    	(player.color == 'Black' and const.BLACK or 0),
    	((player.color ~= 'Grey' and player.color ~= 'Black') and const.PLAYER or 0),
    	(player.promoted and const.PROMOTED or 0),
    	(player.color == 'Grey' and const.SPECTATOR or 0)
    )
    return bit32.band(bit32.bor(table.unpack(config.ACCESS)), rights) ~= 0
end

function checkForUpdate()
    WebRequest.put('".\URL_ROOT."version', TRH_Meta, function(res)
        if (not(res.is_error)) then
            local response = JSON.decode(res.text)
            if (response.errors ~= nil) then
                needsUpdate = const.BADCONNECT
            elseif (response.result ~= nil) then
                local result = response.result
                if (result.update) then
                    if (metaconfig.AUTOUPDATE or false) then
                        installUpdate()
                    elseif (needsUpdate == const.UNKNOWN) then
                        print('[ffcc33]There is a new version of The MiniHUD Controller available[-] ('..result.current..' -> '..result.new..')')
                        print('[0088ff]Please be sure to update[-]')
                    end
                    needsUpdate = const.NEEDSUPDATE
                    TRH_Version = result.current
                    TRH_Version_Next = result.new
                    TRH_Version_Changes = result.changes or {}
                else
                    needsUpdate = const.UPTODATE
                    TRH_Version = result.current
                    TRH_Version_Next = result.new
                    TRH_Version_Changes = {}
                end
                if (ui_mode == 'SETTINGS') then
                    rebuildUI()
                end
            else
                error('something went wrong with JSON parsing')
                log(res.text)
            end
        else
            error(res)
        end
    end)
end

function installUpdate()
    print('[ffcc33]installing update for miniHUD Controller[-]')
    WebRequest.put('".\URL_ROOT."minicontroller/build', TRH_Meta, function(res)
        if (not(res.is_error)) then
            local status = string.sub(res.text, 1, 5)
            if (status == '[SCS]') then
                local result = string.sub(res.text, 15)
                local sig = '--ThatRobHuman MiniHUDController'
                if (string.sub(result, 1, string.len(sig)) == sig) then
                    self.setLuaScript(result)
                    self.reload()
                    print('[33ff33]Installation Successful[-]')
                else
                    error('bad parsing')
                end
            else
                print(res.text)
            end
        else
            error(res)
        end
    end)
end

function ui_system_checkupdate(player)
    if (permit(player)) then
        self.UI.setAttribute('meta_update_status', 'color', '#ffcc33')
        self.UI.setAttribute('meta_update_status', 'text', 'Checking for update...')
        checkForUpdate()
    end
end

function ui_system_update(player)
    if (permit(player)) then
        installUpdate();
    end
end

function initiateLink(data)
    local mini = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
    if ((mini.getVar('TRH_Class') or '') ~= 'mini') then error('invalid mini') end
    if (trackMini(data)) then
        mini.call('setController', {guid=self.guid})
    end
end

function initiateUnlink(data)
    local mini = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
    if (mini ~= nil) then
        if (untrackMini(data)) then
            mini.call('unsetController', {})
        end
    end
end

function onObjectDestroy(obj)
    if (mapG2I[obj.guid] ~= nil) then
        local index = mapG2I[obj.guid]
        local tmpG2I = {}
        local tmpI2G = {}
        local ni = 1
        for i,g in pairs(mapI2G) do
            if (i ~= index) then
                tmpG2I[g] = ni
                table.insert(tmpI2G, g)
                ni = ni + 1
            end
        end
        mapG2I = tmpG2I
        mapI2G = tmpI2G

        if (config.TURNS and index == turn) then
            turn = math.max(1, turn - 1);
        end

        rebuildAssets()
        if (ui_mode ~= '0') then
            Wait.frames(rebuildUI, 3)
        end
    end
end

function trackMini(data)
    local mini = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
    if ((mini.getVar('TRH_Class') or '') ~= 'mini') then error('object is not a mini') end
    if (mapG2I[mini.guid] == nil) then
        local index = #mapI2G + 1
        mapG2I[mini.guid] = index
        mapI2G[index] = mini.guid

        local needsAssetRebuild = false
        for i,marker in pairs(mini.call('getMarkers', {}) or {}) do
            if (assetBuffer[marker.url] == nil) then
                needsAssetRebuild = true
            end
        end
        if (needsAssetRebuild) then
            rebuildAssets()
            if (ui_mode ~= '0') then
                Wait.frames(rebuildUI, 3)
            end
        else
            if (ui_mode ~= '0') then
                rebuildUI()
            end
        end
    end
    return true
end

function untrackMini(data)
    local mini = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
    if (mapG2I[mini.guid] ~= nil) then
        local index = mapG2I[mini.guid]
        local tmpG2I = {}
        local tmpI2G = {}
        local ni = 1
        for i,g in pairs(mapI2G) do
            if (i ~= index) then
                tmpG2I[g] = ni
                table.insert(tmpI2G, g)
                ni = ni + 1
            end
        end
        mapG2I = tmpG2I
        mapI2G = tmpI2G

        if (config.TURNS and index == turn) then
            turn = math.max(1, turn - 1);
        end

        rebuildAssets()
        if (ui_mode ~= '0') then
            Wait.frames(rebuildUI, 3)
        end
    end
    return true
end

function verifyLink(data)
    local mini = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
    return mapG2I[mini.guid] ~= nil
end

function reorderList(guid, newIndex)

    local tmpI2G = {}
    local tmpG2I = {}
    local bufferGuid = mapI2G[newIndex]
    local bufferIndex = mapG2I[guid]

    for i,g in pairs(mapI2G) do
        if (i == bufferIndex) then
            tmpI2G[i] = bufferGuid
            tmpG2I[bufferGuid] = i
        elseif (i == newIndex) then
            tmpI2G[i] = guid
            tmpG2I[guid] = i
        else
            tmpI2G[i] = g
            tmpG2I[g] = i
        end
    end
    mapI2G = tmpI2G
    mapG2I = tmpG2I
    if (ui_mode ~= '0') then rebuildUI() end
end

-- SyncFunctions
function syncBars(data)
    if (ui_mode ~= '0') then rebuildUI() end
end

function syncBarValues(data)
    if (ui_mode ~= '0') then
        local mini = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
        local per = (data.maximum == 0) and 0 or (data.current / data.maximum * 100)
        local index = data.index
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_name', 'text', sanitize(data.name))
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_current', 'text', data.current)
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_maximum', 'text', data.maximum)
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_color', 'text', sanitize(data.color))
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_big', 'isOn', data.big)
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_bar', 'fillImageColor', sanitize(data.color))
        self.UI.setAttribute(mini.guid..'_bar_'..index..'_bar', 'percentage', per)
    end
end

function syncMiniMarkers(data)
    rebuildAssets()
    if (ui_mode ~= '0') then
        Wait.frames(rebuildUI, 3)
    end
end

function syncAdjMiniMarker(data)
    if (ui_mode ~= '0') then
        local display = (data.count or 1) == 1 and '' or data.count
        self.UI.setAttribute(data.guid..'_marker_'..data.index..'_count', 'text', display)
    end
end

--Basics

function ui_pingmini(player, guid)
    if (permit(player)) then
        local mini = getObjectFromGUID(guid)
        if (mini ~= nil) then
            if (player.pingTable ~= nil) then
                player.pingTable(mini.getPosition())
            end
        end
    end
end

function ui_follow(player)
    if (permit(player)) then
        local pos = self.getPosition()
        for i,hit in pairs( Physics.cast({ origin = {x=pos.x, y=pos.y + 0, z=pos.z}, direction = {x=0, y=1, z=0}, max_distance = 1 })) do
            if ((hit.hit_object.getVar('TRH_Class') or '') == 'mini') then
                initiateLink({object=hit.hit_object})
                break
            end
        end
    end
end

function ui_unfollow(player)
    if (permit(player)) then
        local pos = self.getPosition()
        for i,hit in pairs( Physics.cast({ origin = {x=pos.x, y=pos.y + 0, z=pos.z}, direction = {x=0, y=1, z=0}, max_distance = 1 })) do
            if ((hit.hit_object.getVar('TRH_Class') or '') == 'mini') then
                if (mapG2I[hit.hit_object.guid] ~= nil) then
                    initiateUnlink({object=hit.hit_object})
                    break
                end
            end
        end
    end
end

function ui_untrack(player, guid)
    if (permit(player)) then
        initiateUnlink({guid=guid})
    end
end

function ui_reorder(player, params)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(params, '([^%_]+)') do
            table.insert(args,a)
        end
        local guid = args[1]
        local newIndex = tonumber(args[2])
        reorderList(guid, newIndex)
    end
end

function ui_turn(player, adjust)
    if (permit(player)) then
        adjust = (tonumber(adjust) or 1)
        turn = turn + adjust
        rebuildUI()
    end
end

--Bars

function ui_updateminibar(player, value, id)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(id, '([^%_]+)') do
            table.insert(args,a)
        end
        local guid = args[1]
        local type = args[2]
        local index = tonumber(args[3])
        local key = args[4]

        local mini = getObjectFromGUID(guid)
        if (mini ~= nil) then
            if (mapG2I[guid] ~= nil) then
                if (type == 'bar') then
                    if (key == 'big') then value = (value == 'True') end
                    if (key == 'text') then value = (value == 'True') end
                    toSet = {index=index}
                    toSet[key] = value
                    mini.call('editBar', toSet)
                end
            end
        end
    end
end

function ui_removeminibar(player, value)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(value, '([^%_]+)') do
            table.insert(args,a)
        end
        local guid = args[1]
        local index = tonumber(args[2])
        if (mapG2I[guid] ~= nil) then
            local mini = getObjectFromGUID(guid)
            if (mini ~= nil and (mini.getVar('TRH_Class') or '' == 'mini')) then
                mini.call('removeBar', {index=index})
            end
        end
    end
end

function ui_addminibar(player, guid)
    if (permit(player)) then
        if (mapG2I[guid] ~= nil) then
            local mini = getObjectFromGUID(guid)
            if (mini ~= nil and (mini.getVar('TRH_Class') or '' == 'mini')) then
                mini.call('addBar', {})
            end
        end
    end
end

function ui_adjbar(player, params)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(params, '([^%_]+)') do
            table.insert(args,a)
        end
        local guid = args[1]
        local index = tonumber(args[2])
        local amount = tonumber(args[3])
        if (mapG2I[guid] ~= nil) then
            local mini = getObjectFromGUID(guid)
            if (mini ~= nil and (mini.getVar('TRH_Class') or '' == 'mini')) then
                mini.call('adjustBar', {index=index, amount=amount})
            end
        end
    end
end

-- Markers

function ui_popmarker(player, params)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(params, '([^%_]+)') do
            table.insert(args,a)
        end
        local guid = args[1]
        local index = tonumber(args[2])
        if (mapG2I[guid] ~= nil) then
            local mini = getObjectFromGUID(guid)
            if (mini ~= nil and (mini.getVar('TRH_Class') or '' == 'mini')) then
                mini.call('popMarker', {index=index, amount=1})
            end
        end
    end
end

function rebuildAssets()
    local root = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/ui/';
    local assets = {
        {name='ui_power', url=root..'power.png'},
        {name='ui_gear', url=root..'gear.png'},
        {name='ui_close', url=root..'close.png'},
        {name='ui_plus', url=root..'plus.png'},
        {name='ui_minus', url=root..'minus.png'},
        {name='ui_reload', url=root..'reload.png'},
        {name='ui_location', url=root..'location.png'},
        {name='ui_bars_new', url=root..'bars_new.png'},
        {name='ui_arrow_u', url=root..'arrow_u.png'},
        {name='ui_arrow_d', url=root..'arrow_d.png'},
        {name='ui_arrow_l', url=root..'arrow_l.png'},
        {name='ui_arrow_r', url=root..'arrow_r.png'},
    }

    assetBuffer = {}
    local bufLen = 0
    for idx,guid in pairs(mapI2G) do
        local mini = getObjectFromGUID(guid)
        if (mini ~= nil) then
            for i,marker in pairs(mini.call('getMarkers', {})) do
                if (assetBuffer[marker.url] == nil) then
                    bufLen = bufLen + 1
                    assetBuffer[marker.url] = self.guid..'_mk_'..bufLen
                    table.insert(assets, {name=self.guid..'_mk_'..bufLen, url=marker.url})
                end
            end
        end
    end
    self.UI.setCustomAssets(assets)
end

function rebuildUI()
    local ui = {
        {tag='Defaults', children={
            {tag='Text', attributes={color='#cccccc', fontSize='18', alignment='MiddleLeft'}},
            {tag='InputField', attributes={fontSize='24', preferredHeight='40'}},
            {tag='ToggleButton', attributes={fontSize='18', preferredHeight='40', colors='#ffcc33|#ffffff|#808080|#606060', selectedBackgroundColor='#dddddd', deselectedBackgroundColor='#999999'}},
            {tag='Button', attributes={fontSize='18', preferredHeight='40', colors='#dddddd|#ffffff|#808080|#606060'}},
            {tag='Toggle', attributes={textColor='#cccccc'}},
        }}
    }
    table.insert(ui, {
        tag='button', attributes={onClick=(ui_mode == '0' and 'ui_setmode(MAIN)' or 'ui_setmode(0)'), image='ui_power', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', width='80', height='80', position='0 -320 -60' }
    })
    if (ui_mode == 'MAIN') then
        local minilist = {
            tag='VerticalScrollView',
            attributes={id='scroll', minHeight='800', width='600', inertia=false, scrollSensitivity=4, color='black'},
            children = {
                {tag='VerticalLayout', attributes={childForceExpandHeight=false, contentSizeFitter='vertical', spacing='5', padding='5 5 5 5'}, children={}}
            }
        }
        for index,guid in pairs(mapI2G) do
            local mini = getObjectFromGUID(guid)
            if (mini ~= nil and (mini.getVar('TRH_Class') or '' == 'mini')) then
                local bars = mini.call('getBars', {})
                local markers = mini.call('getMarkers', {})
                local h = (50 + (#bars * 30) + (math.ceil(#markers/9) * 60))
                local c = mini.getColorTint()
                local color = '#'..string.format('%02x', math.ceil(c.r * 255))..string.format('%02x', math.ceil(c.g * 255))..string.format('%02x', math.ceil(c.b * 255))

                local miniui = {
                    tag='verticallayout', attributes={color='#202020', childForceExpandHeight=false, padding=5, spacing=5, flexibleHeight=0}, children={
                        {tag='horizontallayout', attributes={preferredHeight = 40, childForceExpandHeight=false, childForceExpandWidth=false, spacing=5}, children={
                            {tag='verticallayout', attributes={childForceExpandHeight=false, preferredHeight=40, preferredWidth=40, flexibleWidth=0}, children={
                                {tag='button', attributes={minWidth = '40', preferredWidth='20', flexibleWidth=0, onClick='ui_reorder('..guid..'_'..(index-1)..')', interactable=(index ~= 1), image='ui_arrow_u'}},
                                {tag='button', attributes={minWidth = '40', preferredWidth='20', flexibleWidth=0, onClick='ui_reorder('..guid..'_'..(index+1)..')', interactable=(index ~= #mapI2G), image='ui_arrow_d'}},
                            }},
                            {tag='panel', attributes={color=color, preferredWidth = 10, flexibleWidth = 0, preferredHeight=40, minWidth='10'}},
                            {tag='text', attributes={alignment='MiddleLeft', preferredHeight=40, fontSize='24', preferredWidth=10000, text=striptags(mini.getName())}},
                            {tag='button', attributes={minWidth = '40', preferredWidth='40', flexibleWidth=0, onClick='ui_pingmini('..guid..')', image='ui_location'}},
                            {tag='button', attributes={minWidth = '40', preferredWidth='40', flexibleWidth=0, onClick='ui_addminibar('..guid..')', image='ui_bars_new'}},
                            {tag='button', attributes={minWidth = '40', preferredWidth='40', flexibleWidth=0, onClick='ui_untrack('..guid..')', image='ui_close'}},
                        }}
                    }
                }

                if ((config.TURNS or false) and index == turn) then
                    miniui.attributes.color='#303030'
                    miniui.attributes.outlineSize='3 3'
                end

                for i,bar in pairs(bars) do
                    local per = (bar.maximum == 0) and 0 or (bar.current / bar.maximum * 100)
                    table.insert(miniui.children, {
                        tag='horizontallayout', attributes={preferredHeight=30, childForceExpandHeight=false, childForceExpandWidth=false, spacing=5}, children={
                            {tag='InputField', attributes={id=guid..'_bar_'..i..'_name', preferredHeight='30', preferredWidth='160', flexibleWidth=0, fontSize='16', alignment='MiddleRight', text=bar.name, onEndEdit='ui_updateminibar'}},
                            {tag='InputField', attributes={id=guid..'_bar_'..i..'_current', preferredHeight='30', preferredWidth='80', flexibleWidth=0, fontSize='16', alignment='MiddleCenter', offsetXY='150 0', text=bar.current, onEndEdit='ui_updateminibar'}},
                            {tag='Button', attributes={preferredWidth='20', preferredHeight='30', flexibleWidth=0, image='ui_arrow_l', onClick='ui_adjbar('..guid..'_'..i..'_-1)'}},
                            {tag='panel', attributes={preferredHeight='30', preferredWidth='280'}, children={
                                {tag='progressbar', attributes={id=guid..'_bar_'..i..'_bar', width='100%', percentage=per, fillImageColor=bar.color, color='#00000080', textColor='transparent'}},
                            }},
                            {tag='Button', attributes={preferredWidth='20', preferredHeight='30', image='ui_arrow_r', flexibleWidth=0,  onClick='ui_adjbar('..guid..'_'..i..'_1)'}},
                            {tag='InputField', attributes={id=guid..'_bar_'..i..'_maximum', preferredHeight='30', preferredWidth='80', fontSize='16', text=bar.maximum, onEndEdit='ui_updateminibar'}},
                            {tag='InputField', attributes={id=guid..'_bar_'..i..'_color', preferredHeight='30', preferredWidth='160', fontSize='16', text=bar.color, onEndEdit='ui_updateminibar'}},
                            {tag='Toggle', attributes={id=guid..'_bar_'..i..'_big', preferredWidth='30', preferredHeight='30', flexibleWidth=0, isOn=(bar.big), onValueChanged='ui_updateminibar'}},
                            {tag='Toggle', attributes={id=guid..'_bar_'..i..'_text', preferredWidth='30', preferredHeight='30', flexibleWidth=0, isOn=(bar.text), onValueChanged='ui_updateminibar'}},
                            {tag='Button', attributes={minWidth='30', preferredHeight='30', image='ui_close', flexibleWidth=0, onClick='ui_removeminibar('..guid..'_'..i..')'}}
                        }})
                end

                local minimarkers = {}
                if (#markers > 0) then
                    minimarkers = {tag='GridLayout', attributes={cellSize='60 60', spacing='5 5'}, children={}}

                    for i,marker in pairs(markers) do
                        table.insert(minimarkers.children, {
                            tag='panel', attributes={width='60', height='60'}, children={
                                {tag='image', attributes={width='40', height='40', image=assetBuffer[marker.url], color=marker.color, rectAlignment='MiddleCenter'}},
                                {tag='text', attributes={id=(guid..'_marker_'..i..'_count'), width='20', height='20', rectAlignment='UpperLeft', text=marker.count, outlineColor='black', outlineSize='2 2'}},
                                {tag='button', attributes={width='20', height='20', image='ui_close', rectAlignment='UpperRight', onClick='ui_popmarker('..guid..'_'..i..')'}},
                            }
                        })

                    end
                    table.insert(miniui.children, minimarkers)
                end
                table.insert(minilist.children[1].children, miniui)
            end
        end

        local updateMe = {}
        if (needsUpdate == const.NEEDSUPDATE) then
            updateMe = {tag='Button', attributes={fontSize='22', text='Update Available', onClick='ui_setmode(SETTINGS)', flexibleWidth=1, colors = '#ffcc33|#ffffff|#808080|#606060'}}
        end
        local turnpanel = {}
        if (config.TURNS) then
            turnpanel = {tag='VerticalLayout', attributes={childForceExpandWidth=false, flexibleHeight=0, spacing=10, color='black', padding='10'}, children={
                {tag='Text', attributes={text='Initiative', fontSize='24', }},
                {tag='HorizontalLayout', attributes={spacing=10}, children={
                    {tag='Button', attributes={fontSize='22', text='Prev Turn', onClick='ui_turn(-1)', flexibleWidth=1, interactable=(turn > 1)}},
                    {tag='Button', attributes={fontSize='22', text='Next Turn', onClick='ui_turn(1)', flexibleWidth=1, interactable=(turn < #mapI2G )}},
                    {tag='Text', attributes={text='Current Turn: '..turn}},
                }}
            }}
        end
        table.insert(ui, {tag='Panel', attributes={position='0 -400 -60', height='10000', width='800', rectAlignment='UpperCenter'},
            children={
                {tag='VerticalLayout', attributes={childForceExpandHeight=false, minHeight='0', spacing=10, rectAlignment='UpperCenter'}, children={
                    {tag='HorizontalLayout', attributes={preferredHeight=80, childForceExpandWidth=false, flexibleHeight=0, spacing=20, padding='10 10 10 10'}, children={
                        {tag='Button', attributes={fontSize='22', text='Untrack Mini', onClick='ui_unfollow', flexibleWidth=1}},
                        {tag='Button', attributes={fontSize='22', text='Track Mini', onClick='ui_follow', flexibleWidth=1}},
                        updateMe,
                        {tag='button', attributes={onClick='rebuildUI', image='ui_reload', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', preferredWidth='60', preferredHeight='60', flexibleWidth=0, flexibleHeight=0}},
                        {tag='button', attributes={onClick='ui_setmode(SETTINGS)', image='ui_gear', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', preferredWidth='60', preferredHeight='60', flexibleWidth=0, flexibleHeight=0}},
                    }},
                    turnpanel,
                    minilist
                }}
            }
        })
    end
    if (ui_mode == 'SETTINGS') then

        local updateStatusDisplay = {tag='Text', attributes={id='meta_update_status', text='Update Autocheck is disabled', color='#ff3333'}}

        local changeDisplay = {}

        if (needsUpdate == const.UPTODATE) then
            updateStatusDisplay = {tag='Text', attributes={id='meta_update_status', text='MiniHUD Controller is up to date', color='#33ccff'}}
        end
        if (needsUpdate == const.NEEDSUPDATE) then
            updateStatusDisplay = {tag='Text', attributes={id='meta_update_status', text='A new version is available', color='#ffcc33'}}
            local changelog = {{ tag='Text', attributes={fontSize='24', text='Change Log'} }}
            for i,v in pairs(TRH_Version_Changes) do
                table.insert(changelog, { tag='Text', attributes={text=string.char(8226)..' '..v} })
            end
            changeDisplay = {tag='VerticalLayout', attributes={spacing='5', padding='5 5 5 5', childDorceExpandHeight=false}, children=changelog}
        end
        if (needsUpdate == const.BADCONNECT) then
            updateStatusDisplay = {tag='Text', attributes={id='meta_update_status', text='Error connecting to Robinomicon', color='#ff3333'}}
        end

        table.insert(ui, {
            tag='Panel', attributes={position='0 -400 -60', height='10000', width='800', rectAlignment='UpperCenter'}, children={
                {tag='VerticalLayout', attributes={childForceExpandHeight=false, minHeight='0', spacing=5, rectAlignment='UpperCenter'}, children={
                    {tag='HorizontalLayout', attributes={preferredHeight=80, flexibleHeight=0, spacing=20, childForceExpandWidth=false, childAlignment='MiddleRight', padding='10 10 10 10'}, children={
                        {tag='button', attributes={onClick='ui_setmode(MAIN)', image='ui_gear', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', preferredWidth='60', height='80'}}
                    }},
                    {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                        {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Updates'}},
                        {tag='Text', attributes={text='Current Version: '..TRH_Version}},
                        {tag='Text', attributes={text='Next Version: '..TRH_Version_Next}},
                        updateStatusDisplay,
                        {tag='HorizontalLayout', attributes={}, children={
                            {tag='Button', attributes={onClick='ui_system_checkupdate', text='Check for Update'}},
                            {tag='Button', attributes={colors='#ffcc33|#ffffff|#808080|#606060', onClick='ui_system_update', text='Update and Restart MiniHUD Controller', interactable = (needsUpdate == const.NEEDSUPDATE)}},
                        }},
                        {tag='HorizontalLayout', attributes={childForceExpandWidth=false, spacing=5}, children={
                            {tag='Button', attributes={preferredWidth='30', preferredHeight='30', flexibleWidth=0, image=(metaconfig.UPDATECHECK and 'ui_checkon' or 'ui_checkoff'), onClick='ui_meta_toggle(UPDATECHECK)', id='tgl_settings_updatecheck'}},
                            {tag='Text', attributes={preferredWidth='30', flexibleWidth=1, text='Auto-check for Updates'}},
                            {tag='Button', attributes={preferredWidth='30', preferredHeight='30', flexibleWidth=0, image=(metaconfig.AUTOUPDATE and 'ui_checkon' or 'ui_checkoff'), onClick='ui_meta_toggle(AUTOUPDATE)', id='tgl_settings_autoupdate'}},
                            {tag='Text', attributes={preferredWidth='30', flexibleWidth=1, text='Automatically Update'}},
                        }},
                        changeDisplay
                    }}
                }}
            }
        })
    end
    self.UI.setXmlTable(ui)
end

function onSave()
    local save = {
        minis={},
        turn = turn or 1,
        metaconfig = metaconfig
    }
    for index, guid in pairs(mapI2G) do
        table.insert(save.minis, guid)
    end
    return JSON.encode(save)
end

function verifySpawning(minilist)
    for i,guid in pairs(minilist or {}) do
        local m = getObjectFromGUID(guid)
        if (m ~= nil) then
            if (m.spawning) then
                return false
            end
        end
    end
    return true
end

function loadLinkages(minilist)
    for _, guid in pairs(minilist or {}) do
        local mini = getObjectFromGUID(guid)
        if (mini ~= nil) then
            if ((mini.getVar('TRH_Class') or '') == 'mini') then
                if (mini.call('verifyLink', {object=self})) then
                    mapG2I[guid] = index
                    mapI2G[index] = guid
                    index = index + 1
                else
                    initiateLink({guid=guid})
                end
            end
        end
    end
    Wait.frames(function()
        rebuildAssets()
        Wait.frames(rebuildUI, 3)
    end, 3)
end

function onLoad(save)
    local data = JSON.decode(save)
    turn = data.turn or 1
    metaconfig = data.metaconfig or metaconfig
    if (metaconfig.UPDATECHECK) then
        checkForUpdate()
    end

    mapG2I = {}
    mapI2G = {}

    local index = 1;
    Wait.condition(function() loadLinkages(data.minis) end, function() return verifySpawning(data.minis) end)
end

";
        }

    }
}

?>
