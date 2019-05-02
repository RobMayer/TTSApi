<?php

namespace handlers {
    class MiniInjector {
        public function build() {
            return "--ThatRobHuman MiniHUDInjector
            TRH_Class = 'mini.injector'
            local TRH_Version = '".\UTILITY_VERSIONS['mini.injector'][0]['version']."'
            local TRH_Version_Next = '???'
            local TRH_Version_Changes = {}
            local TRH_Meta = '".\lib\VersionManager::token('mini.injector')."'
            local const = { SPECTATOR = 1, PLAYER = 2, PROMOTED = 4, BLACK = 8, HOST = 16, ALL = 31, NOSPECTATOR = 30, OFF = 0, INCREMENTAL = 1, STATIC = 2, BRACKETS = 3, SIMPLEGAUGE = 1, RADIUS = 2, COMPLEXGAUGE = 3, DEFINED = 4, PLASTIC = 0, WOOD = 1, METAL = 2, CARDBOARD = 3, UNKNOWN = 0, UPDATENEEDED = 1, UPTODATE = 2, BADCONNECT = 3, SHIELD_FRONTBACK = 1, SHIELD_LEFTRIGHT = 2, SHIELD_FOURWAY = 3, SHIELD_SIXWAY = 4, SHIELD_CAPATLIMIT = 1, SHIELD_WRAPAROUND = 2, SHIELD_IGNORELIMIT = 3}

local needsUpdate = const.UNKNOWN;

local users = {
        Grey = 1,
        Host = 2,
        Admin = 4,
        Black = 8,
        White = 16,
        Brown = 32,
        Red = 64,
        Orange = 128,
        Yellow = 256,
        Green = 512,
        Teal = 1024,
        Blue = 2048,
        Purple = 4096,
        Pink = 8192,
        Clubs = 16384,
        Diamonds = 32768,
        Hearts = 65536,
        Spades = 131072,
        Jokers = 262144,
}

local arcshapes = {
    'round0',
    'round4',
    'round6',
    'round8',
    'round12',
    'hex0',
    'hex6',
    'hex12',
}

local currentconfig = {
    REFRESH = 3,
    UI_SCALE = 1.0,
    BASE_WIDTH = 1.5,
    BASE_LENGTH = 1.5,
    OVERHEAD_HEIGHT = 2,
    OVERHEAD_OFFSET = 0,
    OVERHEAD_WIDTH = 3,
    OVERHEAD_ORIENT = 'VERTICAL',
    PERMEDIT = 10,
    PERMVIEW = 524287,
    MODULE_BARS = true,
    MODULE_MARKERS = true,
    MODULE_ARC = false,
    MODULE_FLAG = false,
    MODULE_GEOMETRY = false,
    MODULE_MOVEMENT = false,
    MODULE_SHIELDS = false,
    ARCS = {
        MODE = 1,
        ZERO = 0,
        SHAPE = 1,
        MESH = '',
        BRACKETS = {},
        SCALE = 1,
        COLOR = 'inherit',
        MAX = 16,
    },
    BARS={},
    FLAG={},
    LOCK_FLAG = false,
    LOCK_GEOMETRY = false,
    GEOMETRY={
        MESH = '',
        TEXTURE = '',
        NORMAL = '',
        COLOR = 'inherit',
        MATERIAL = 0,
    },
    MOVEMENT = {
        MODE = 1,
        UIHEIGHT = 0.25,
        SPEEDDISTANCE = 1,
        SPEEDMIN = 0,
        SPEEDMAX = 4,
        TURNNOTCH = 22.5,
        TURNMAX = 3,
        COLOR = 'inherit',
        LANDSHOW = true,
        LANDTEST = false,
        ORIGIN = 'EDGE',
        SEGMENTS = {
            {0,{}},
        },
        DEFINITIONS = {
            {'Standstill','https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/ui/move/standstill.png', 0,0,0,2,0,'#0088ff'},
        }
    },
    SHIELDS = {
        SHAPE = 1,
        CURRENT = {6,6,6,6,6,6},
        LIMIT = {6,6,6,6,6,6},
        CRITICAL = {1,1,1,1,1,1},
        COLOR = '#1f87ff',
        UIHEIGHT = 0.25,
        CRITCOLOR = '#da1918',
        LIMITMODE = 1,
        AUTOMODE = true,
    }
}
local currentpresets = {}
local metaconfig = {
    UPDATECHECK = true,
    AUTOUPDATE = false,
}

config = {
    ACCESS = {const.PLAYER, const.HOST},
    REFRESH = 6
}

local sectionVis = {
    presets = false,
    edit_permissions = false,
    view_permissions = false,
    bars = false,
    markers = false,
    arcs = false,
    flag = false,
    geometry = false,
    movement = false,
    shields = false,
}

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

local clone = function(obj, seen)
    if type(obj) ~= 'table' then
        return obj
    end
    if seen and seen[obj] then return seen[obj] end
        local s = seen or {}
        local res = setmetatable({}, getmetatable(obj))
        s[obj] = res
        for k, v in pairs(obj) do
            res[clone(k, s)] = clone(v, s)
        end
    return res
end

function base64_decode(data)
    local b = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
    data = string.gsub(data, '[^'..b..'=]', '')
    return (data:gsub('.', function(x)
        if (x == '=') then return '' end
        local r,f='',(b:find(x)-1)
        for i=6,1,-1 do r=r..(f%2^i-f%2^(i-1)>0 and '1' or '0') end
        return r;
    end):gsub('%d%d%d?%d?%d?%d?%d?%d?', function(x)
        if (#x ~= 8) then return '' end
        local c=0
        for i=1,8 do c=c+(x:sub(i,i)=='1' and 2^(8-i) or 0) end
        return string.char(c)
    end))
end

function extract(obj)
    local s = obj.getVar('TRH_Save')
    if (s ~= nil and s ~= '') then
        currentconfig = JSON.decode(base64_decode(s))
        rebuildUI()
    end
end

function ui_extract(player)
    if (permit(player)) then
        local pos = self.getPosition()
        for i,hit in pairs( Physics.cast({ origin = {x=pos.x, y=pos.y + 0, z=pos.z}, direction = {x=0, y=1, z=0}, max_distance = 1 })) do
            if (hit.hit_object.getVar('TRH_Class') == 'mini') then
                extract(hit.hit_object)
                break
            end
        end
    end
end

function installUpdate()
    print('[ffcc33]installing update for miniHUD Injector[-]')
    WebRequest.put('".\URL_ROOT."minihud/build', TRH_Meta, function(res)
        if (not(res.is_error)) then
            local status = string.sub(res.text, 1, 5)
            if (status == '[SCS]') then
                local result = string.sub(res.text, 15)
                local sig = '--ThatRobHuman MiniHUDInjector'
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

function ui_inject(player)
    if (permit(player)) then
        local pos = self.getPosition()
        for i,hit in pairs( Physics.cast({ origin = {x=pos.x, y=pos.y + 0, z=pos.z}, direction = {x=0, y=1, z=0}, max_distance = 1 })) do
            if (hit.hit_object.getVar('TRH_Class') == nil) then
                inject(hit.hit_object)
                break
            end
        end
    end
end

function ui_clearmini(player)
    if (permit(player)) then
        local pos = self.getPosition()
        for i,hit in pairs( Physics.cast({ origin = {x=pos.x, y=pos.y + 0, z=pos.z}, direction = {x=0, y=1, z=0}, max_distance = 1 })) do
            if ((hit.hit_object.getVar('TRH_Class') or '') == 'mini') then
                clearmini(hit.hit_object)
                break
            end
        end
    end
end

function ui_togglelock(player, value, id)
    if (permit(player)) then
        local key = 'LOCK_'..value;
        local c = currentconfig[key];
        currentconfig[key] = not(c)
        if (currentconfig[key]) then
            self.UI.setAttribute(id, 'image', 'ui_locked')
        else
            self.UI.setAttribute(id, 'image', 'ui_unlocked')
        end
    end
end

function getInjectionState()
    local toInject = {}
    if (currentconfig.MODULE_BARS) then
        toInject.bars = currentconfig.BARS
    end
    if (currentconfig.MODULE_FLAG) then
        toInject.flag = {
            image = currentconfig.FLAG.IMAGE or '',
            color = currentconfig.FLAG.COLOR or '#ffffff',
            width = currentconfig.FLAG.WIDTH or 0,
            height = currentconfig.FLAG.HEIGHT or 0,
            automode = currentconfig.FLAG.AUTOMODE or false,
        }
    end
    if (currentconfig.MODULE_GEOMETRY == true) then
        toInject.geometry = {
            mesh = currentconfig.GEOMETRY.MESH or '',
            texture = currentconfig.GEOMETRY.TEXTURE or '',
            normal = currentconfig.GEOMETRY.NORMAL or '',
            color = currentconfig.GEOMETRY.COLOR or 'inherit',
        }
    end
    if (currentconfig.MODULE_SHIELDS) then
        toInject.shields = {}
        if (currentconfig.SHIELDS.SHAPE == const.SHIELD_FRONTBACK) then
            toInject.shields.current = {table.unpack(currentconfig.SHIELDS.CURRENT, 1, 2)}
            toInject.shields.limit = {table.unpack(currentconfig.SHIELDS.LIMIT, 1, 2)}
            toInject.shields.critical = {table.unpack(currentconfig.SHIELDS.CRITICAL, 1, 2)}
        end
        if (currentconfig.SHIELDS.SHAPE == const.SHIELD_LEFTRIGHT) then
            toInject.shields.current = {table.unpack(currentconfig.SHIELDS.CURRENT, 1, 2)}
            toInject.shields.limit = {table.unpack(currentconfig.SHIELDS.LIMIT, 1, 2)}
            toInject.shields.critical = {table.unpack(currentconfig.SHIELDS.CRITICAL, 1, 2)}
        end
        if (currentconfig.SHIELDS.SHAPE == const.SHIELD_FOURWAY) then
            toInject.shields.current = {table.unpack(currentconfig.SHIELDS.CURRENT, 1, 4)}
            toInject.shields.limit = {table.unpack(currentconfig.SHIELDS.LIMIT, 1, 4)}
            toInject.shields.critical = {table.unpack(currentconfig.SHIELDS.CRITICAL, 1, 4)}
        end
        if (currentconfig.SHIELDS.SHAPE == const.SHIELD_SIXWAY) then
            toInject.shields.current = {table.unpack(currentconfig.SHIELDS.CURRENT, 1, 6)}
            toInject.shields.limit = {table.unpack(currentconfig.SHIELDS.LIMIT, 1, 6)}
            toInject.shields.critical = {table.unpack(currentconfig.SHIELDS.CRITICAL, 1, 6)}
        end
        toInject.shields.color = currentconfig.SHIELDS.COLOR
        toInject.shields.critcolor = currentconfig.SHIELDS.CRITCOLOR
        toInject.shields.automode = currentconfig.SHIELDS.AUTOMODE or false
    end
    return JSON.encode(toInject)
end

function getInjectionConfig()
    local tmp = {
        REFRESH = currentconfig.REFRESH,
        UI_SCALE = currentconfig.UI_SCALE,
        BASE_LENGTH = currentconfig.BASE_LENGTH,
        BASE_WIDTH = currentconfig.BASE_WIDTH,
        OVERHEAD_ORIENT = currentconfig.OVERHEAD_ORIENT,
        OVERHEAD_OFFSET = currentconfig.OVERHEAD_OFFSET,
        OVERHEAD_WIDTH = currentconfig.OVERHEAD_WIDTH,
        OVERHEAD_HEIGHT = currentconfig.OVERHEAD_HEIGHT,
        MODULE_BARS = currentconfig.MODULE_BARS,
        MODULE_MARKERS = currentconfig.MODULE_MARKERS,
        MODULE_ARC = currentconfig.MODULE_ARC,
        MODULE_FLAG = currentconfig.MODULE_FLAG,
        MODULE_GEOMETRY = currentconfig.MODULE_GEOMETRY,
        MODULE_MOVEMENT = currentconfig.MODULE_MOVEMENT,
        MODULE_SHIELDS = currentconfig.MODULE_SHIELDS,
    }

    if (currentconfig.MODULE_MOVEMENT == true) then
        tmp.MOVEMENT = {}
        tmp.MOVEMENT.MODE = currentconfig.MOVEMENT.MODE
        tmp.MOVEMENT.UIHEIGHT = currentconfig.MOVEMENT.UIHEIGHT
        if (currentconfig.MOVEMENT.MODE == const.SIMPLEGAUGE) then
            tmp.MOVEMENT.LANDSHOW = currentconfig.MOVEMENT.LANDSHOW
            tmp.MOVEMENT.LANDTEST = currentconfig.MOVEMENT.LANDTEST
            tmp.MOVEMENT.SPEEDDISTANCE = currentconfig.MOVEMENT.SPEEDDISTANCE
            tmp.MOVEMENT.SPEEDMAX = currentconfig.MOVEMENT.SPEEDMAX
            tmp.MOVEMENT.TURNNOTCH = currentconfig.MOVEMENT.TURNNOTCH
            tmp.MOVEMENT.TURNMAX = currentconfig.MOVEMENT.TURNMAX
        elseif (currentconfig.MOVEMENT.MODE == const.RADIUS) then
            tmp.MOVEMENT.SPEEDMIN = currentconfig.MOVEMENT.SPEEDMIN
            tmp.MOVEMENT.SPEEDMAX = currentconfig.MOVEMENT.SPEEDMAX
            tmp.MOVEMENT.ORIGIN = currentconfig.MOVEMENT.ORIGIN
        elseif (currentconfig.MOVEMENT.MODE == const.COMPLEXGAUGE) then
            tmp.MOVEMENT.LANDSHOW = currentconfig.MOVEMENT.LANDSHOW
            tmp.MOVEMENT.LANDTEST = currentconfig.MOVEMENT.LANDTEST
            tmp.MOVEMENT.SEGMENTS = currentconfig.MOVEMENT.SEGMENTS
        elseif (currentconfig.MOVEMENT.MODE == const.DEFINED) then
            tmp.MOVEMENT.LANDSHOW = currentconfig.MOVEMENT.LANDSHOW
            tmp.MOVEMENT.LANDTEST = currentconfig.MOVEMENT.LANDTEST
            tmp.MOVEMENT.DEFINITIONS = currentconfig.MOVEMENT.DEFINITIONS
        end
    end
    if (currentconfig.MODULE_ARC == true) then
        tmp.ARCS = {}
        tmp.ARCS.MODE = currentconfig.ARCS.MODE
        if (currentconfig.ARCS.MODE == const.INCREMENTAL) then
            if (currentconfig.ARCS.SHAPE == -1) then
                tmp.ARCS.MESH = currentconfig.ARCS.MESH
            else
                tmp.ARCS.MESH = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/components/arcs/'..(arcshapes[currentconfig.ARCS.SHAPE])..'.obj'
            end
            tmp.ARCS.SCALE = currentconfig.ARCS.SCALE
            tmp.ARCS.COLOR = currentconfig.ARCS.COLOR
            tmp.ARCS.MAX = currentconfig.ARCS.MAX
            tmp.ARCS.ZERO = currentconfig.ARCS.ZERO
        elseif (currentconfig.ARCS.MODE == const.STATIC) then
            if (currentconfig.ARCS.SHAPE == -1) then
                tmp.ARCS.MESH = currentconfig.ARCS.MESH
            else
                tmp.ARCS.MESH = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/components/arcs/'..(arcshapes[currentconfig.ARCS.SHAPE])..'.obj'
            end
            tmp.ARCS.SCALE = currentconfig.ARCS.SCALE
            tmp.ARCS.COLOR = currentconfig.ARCS.COLOR
        elseif (currentconfig.ARCS.MODE == const.BRACKETS) then
            if (currentconfig.ARCS.SHAPE == -1) then
                tmp.ARCS.MESH = currentconfig.ARCS.MESH
            else
                tmp.ARCS.MESH = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/components/arcs/'..(arcshapes[currentconfig.ARCS.SHAPE])..'.obj'
            end
            tmp.ARCS.SCALE = currentconfig.ARCS.SCALE
            tmp.ARCS.COLOR = currentconfig.ARCS.COLOR
            tmp.ARCS.BRACKETS = currentconfig.ARCS.BRACKETS
            tmp.ARCS.ZERO = currentconfig.ARCS.ZERO
        end
    end
    if (currentconfig.MODULE_FLAG) then
        tmp.LOCK_FLAG = currentconfig.LOCK_FLAG
    end
    if (currentconfig.MODULE_GEOMETRY) then
        tmp.LOCK_GEOMETRY = currentconfig.LOCK_GEOMETRY
    end
    if (currentconfig.MODULE_SHIELDS) then
        tmp.SHIELDS = {
            SHAPE = currentconfig.SHIELDS.SHAPE,
            UIHEIGHT = currentconfig.SHIELDS.UIHEIGHT,
            LIMITMODE = currentconfig.SHIELDS.LIMITMODE
        }
    end

    tmpEdit = {}
    tmpView = {}
    for perm,flag in pairs(users) do
        if (bit32.band(currentconfig.PERMEDIT, flag) ~= 0) then table.insert(tmpEdit, perm) end
        if (bit32.band(currentconfig.PERMVIEW, flag) ~= 0) then table.insert(tmpView, perm) end
    end
    tmp.PERMEDIT = table.concat(tmpEdit, '|')
    tmp.PERMVIEW = table.concat(tmpView, '|')
    return tmp
end

function inject(obj)
    local req = WebRequest.put('".\URL_ROOT."minihud/inject', JSON.encode({inject=getInjectionConfig(), config=currentconfig}), function(res)
        if (not(res.is_error)) then
            local status = string.sub(res.text, 1, 5)
            if (status == '[SCS]') then
                local result = string.sub(res.text, 15)
                local sig = '--ThatRobHuman MiniHUD'
                if (string.sub(result, 1, string.len(sig)) == sig) then
                    obj.script_state = getInjectionState()
                    obj.setLuaScript(result)
                    obj.reload()
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

function clearmini(obj)
    obj.script_state = ''
    obj.setLuaScript('')
    obj.reload()
end

local ui_mode = '0'

function ui_setmode(player, value, id)
    ui_mode = value
    rebuildUI()
end

function toggle_module(player, value, id)
    local key = 'MODULE_'..value
    currentconfig[key] = not(currentconfig[key])
    if (currentconfig[key]) then
        self.UI.setAttribute(id, 'image', 'ui_checkon')
    else
        self.UI.setAttribute(id, 'image', 'ui_checkoff')
    end

    rebuildUI()
end

function ui_togglesection(player, section)
    sectionVis[section] = not(sectionVis[section])
    rebuildUI()
end

-- BASICS

function ui_editbasic(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'basewidth') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.BASE_WIDTH = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'baselength') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.BASE_LENGTH = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'scale') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.UI_SCALE = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'refresh') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.REFRESH = n end
        self.UI.setAttribute(id, 'text', value)
    end
end

-- Permissions

function ui_permview_toggle(player, flag, id)
    currentconfig.PERMVIEW = bit32.bxor(currentconfig.PERMVIEW, flag)
    self.UI.setAttribute(id, 'isOn', (self.UI.getAttribute(id, 'isOn') == 'true') and 'false' or 'true')
end
function ui_permview_set(player, value)
    currentconfig.PERMVIEW = value
    for lvl,flag in pairs(users) do
        self.UI.setAttribute('inp_perm_view_'..flag, 'isOn', (bit32.band(flag, value) == 0) and 'false' or 'true')
    end
end
function ui_permedit_toggle(player, flag, id)
    currentconfig.PERMEDIT = bit32.bxor(currentconfig.PERMEDIT, flag)
    self.UI.setAttribute(id, 'isOn', (self.UI.getAttribute(id, 'isOn') == 'true') and 'false' or 'true')
end
function ui_permedit_set(player, value)
    currentconfig.PERMEDIT = value
    for lvl,flag in pairs(users) do
        self.UI.setAttribute('inp_perm_edit_'..flag, 'isOn', (bit32.band(flag, value) == 0) and 'false' or 'true')
    end
end

--BARS

function ui_addbar(player)
    table.insert(currentconfig.BARS, {'Name', '#ffffff', 5, 10, false, false})
    rebuildUI()
end

function ui_editbar(player, val, id)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(id, '([^%_]+)') do
            table.insert(args,a)
        end
        local index = tonumber(args[3])
        local key = args[4]
        if (key == 'name') then
            currentconfig.BARS[index][1] = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'color') then
            currentconfig.BARS[index][2] = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'current') then
            local n = tonumber(val)
            if (n ~= nil) then
                n = math.min(n, currentconfig.BARS[index][4])
                currentconfig.BARS[index][3] = n
                self.UI.setAttribute(id, 'text', n)
            else
                self.UI.setAttribute(id, 'text', val)
            end
        elseif (key == 'maximum') then
            local n = tonumber(val)
            if (n ~= nil) then
                currentconfig.BARS[index][4] = n
                if (currentconfig.BARS[index][3] > n) then
                    currentconfig.BARS[index][3] = n
                    self.UI.setAttribute('inp_bar_'..index..'_current', 'text', n)
                end
                self.UI.setAttribute(id, 'text', n)
            else
                self.UI.setAttribute(id, 'text', val)
            end
        elseif (key == 'text') then
            currentconfig.BARS[index][5] = not(currentconfig.BARS[index][5])
            if (currentconfig.BARS[index][5]) then
                self.UI.setAttribute(id, 'image', 'ui_checkon')
            else
                self.UI.setAttribute(id, 'image', 'ui_checkoff')
            end
        elseif (key == 'big') then
            currentconfig.BARS[index][6] = not(currentconfig.BARS[index][6])
            if (currentconfig.BARS[index][6]) then
                self.UI.setAttribute(id, 'image', 'ui_checkon')
            else
                self.UI.setAttribute(id, 'image', 'ui_checkoff')
            end
        end
    end
end

function ui_rembar(player, index)
    index = tonumber(index) or error('invalid index')
    local tmp = {}
    for i,bar in pairs(currentconfig.BARS) do
        if (i ~= index) then
            table.insert(tmp, bar)
        end
    end
    currentconfig.BARS = tmp
    rebuildUI()
end

--ARCS

function ui_arcmode(player, val)
    currentconfig.ARCS.MODE = tonumber(val) or 0
    rebuildUI()
end

function ui_editarc(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    if (args[3] == 'scale') then
        value = tonumber(value)
        currentconfig.ARCS.SCALE = value
    end
    if (args[3] == 'color') then
        currentconfig.ARCS.COLOR = value
    end
    if (args[3] == 'mesh') then
        currentconfig.ARCS.MESH = value
    end
    if (args[3] == 'max') then
        value = tonumber(value)
        currentconfig.ARCS.MAX = value
    end
    if (args[3] == 'zero') then
        value = tonumber(value)
        currentconfig.ARCS.ZERO = value
    end
    self.UI.setAttribute(id, 'text', value)
end

function ui_editarc_shape(player, value, id)
    value = tonumber(value)
    if (value == -1) then
        self.UI.show('cnt_arc_mesh')
        self.UI.setAttribute('inp_arc_shape_-1', 'isOn', true)
    else
        self.UI.hide('cnt_arc_mesh')
        self.UI.setAttribute('inp_arc_shape_-1', 'isOn', false)
    end
    for i,shape in pairs(arcshapes) do
        self.UI.setAttribute('inp_arc_shape_'..i, 'isOn', i == value)
    end
    currentconfig.ARCS.SHAPE = value
end

function ui_addarcbracket(player)
    table.insert(currentconfig.ARCS.BRACKETS, 5)
    rebuildUI()
end

function ui_editarcbracket(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local index = tonumber(args[3])
    currentconfig.ARCS.BRACKETS[index] = value
    self.UI.setAttribute(id, 'text', value)
end

function ui_remarcbracket(player)
    tmp = {}
    for i,v in pairs(currentconfig.ARCS.BRACKETS) do
        if (i ~= #currentconfig.ARCS.BRACKETS) then
            tmp[i] = v
        end
    end
    currentconfig.ARCS.BRACKETS = tmp
    rebuildUI()
end

function ui_arc_setcolor(player, value, id)
    ui_editarc(player, value, 'inp_arc_color');
end

--FLAG

function ui_editflag(player, val, id)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(id, '([^%_]+)') do
            table.insert(args,a)
        end
        local key = args[3]
        if (key == 'image') then
            currentconfig.FLAG.IMAGE = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'color') then
            currentconfig.FLAG.COLOR = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'width') then
            local n = tonumber(val)
            if (n ~= nil) then
                currentconfig.FLAG.WIDTH = n
                self.UI.setAttribute(id, 'text', n)
            else
                self.UI.setAttribute(id, 'text', val)
            end
        elseif (key == 'height') then
            local n = tonumber(val)
            if (n ~= nil) then
                currentconfig.FLAG.HEIGHT = n
                self.UI.setAttribute(id, 'text', n)
            else
                self.UI.setAttribute(id, 'text', val)
            end
        elseif (key == 'automode') then
            currentconfig.FLAG.AUTOMODE = not(currentconfig.FLAG.AUTOMODE)
            if (currentconfig.FLAG.AUTOMODE) then
                self.UI.setAttribute(id, 'image', 'ui_checkon')
            else
                self.UI.setAttribute(id, 'image', 'ui_checkoff')
            end
        end
    end
end

--Geometry

function ui_flag_setcolor(player, val, id)
    ui_editflag(player, val, 'inp_flag_color')
end

function ui_editgeometry(player, val, id)
    if (permit(player)) then
        local args = {}
        for a in string.gmatch(id, '([^%_]+)') do
            table.insert(args,a)
        end
        local key = args[3]
        if (key == 'mesh') then
            currentconfig.GEOMETRY.MESH = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'color') then
            currentconfig.GEOMETRY.COLOR = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'texture') then
            currentconfig.GEOMETRY.TEXTURE = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'normal') then
            currentconfig.GEOMETRY.NORMAL = val
            self.UI.setAttribute(id, 'text', val)
        elseif (key == 'material') then
            local n = tonumber(val)
            currentconfig.GEOMETRY.MATERIAL = n
            self.UI.setAttribute('inp_geometry_material_0', 'isOn', n == 0)
            self.UI.setAttribute('inp_geometry_material_1', 'isOn', n == 1)
            self.UI.setAttribute('inp_geometry_material_2', 'isOn', n == 2)
            self.UI.setAttribute('inp_geometry_material_3', 'isOn', n == 3)
        end
    end
end

function ui_geometry_setcolor(player, value, id)
    ui_editgeometry(player, value, 'inp_geometry_color');
end

--BASICS

function ui_editoverhead(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'height') then
        currentconfig.OVERHEAD_HEIGHT = tonumber(value) or 1
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'offset') then
        currentconfig.OVERHEAD_OFFSET = tonumber(value) or 0
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'width') then
        currentconfig.OVERHEAD_WIDTH = tonumber(value) or 2
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'orient') then
        local dir = string.upper(args[4])
        currentconfig.OVERHEAD_ORIENT = dir or 'VERTICAL'
        if (dir == 'VERTICAL') then
            self.UI.setAttribute('inp_overhead_orient_horizontal', 'isOn', false)
            self.UI.setAttribute('inp_overhead_orient_vertical', 'isOn', true)
        else
            self.UI.setAttribute('inp_overhead_orient_horizontal', 'isOn', true)
            self.UI.setAttribute('inp_overhead_orient_vertical', 'isOn', false)
        end
    end
end


function ui_setuiscale(player, value, id)
    currentconfig.UI_SCALE = tonumber(value) or 1
    self.UI.setAttribute(id, 'text', value)
end

function ui_setdelay(player, value, id)
    currentconfig.REFRESH = tonumber(value)
    self.UI.setAttribute(id, 'text', value)
end

--PRESETS

function ui_presetadd(player)
    table.insert(currentpresets, {
        name='',
        config=clone(currentconfig)
    })
    rebuildUI()
end

function ui_presetrename(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local index = args[3]
    currentpresets[tonumber(index)].name = value
    self.UI.setAttribute(id, 'text', value)
end

function ui_presetsave(player, index)
    local i = tonumber(index)
    currentpresets[i].config = clone(currentconfig)
end

function ui_presetload(player, index)
    local i = tonumber(index)
    currentconfig = clone(currentpresets[i].config)
    rebuildUI()
end

function ui_presetdelete(player, index)
    local tmp = {}
    local theIndex = tonumber(index)
    for i,preset in pairs(currentpresets) do
        if (i ~= theIndex) then
            table.insert(tmp, preset)
        end
    end
    currentpresets = tmp
    rebuildUI()
end

--SHIELDS

function ui_shieldsshape(player, val)
    currentconfig.SHIELDS.SHAPE = tonumber(val) or 0
    rebuildUI()
end

function ui_shieldslimitmode(player, val)
    currentconfig.SHIELDS.LIMITMODE = tonumber(val) or 0
    rebuildUI()
end

function ui_shields_setcolor(player, value, id)
    ui_editshields(player, value, 'inp_shields_color')
end

function ui_shields_setcritcolor(player, value, id)
    ui_editshields(player, value, 'inp_shields_critcolor')
end

function ui_editshields(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'current' or key == 'limit' or key == 'critical') then
        local index = tonumber(args[4]) or error('index is required when modifying current, limit, or critical')
        local n = tonumber(value)
        if (n ~= nil) then
            currentconfig.SHIELDS[string.upper(key)][index] = n
            self.UI.setAttribute(id, 'text', n)
        else
            self.UI.setAttribute(id, 'text', value)
        end
    end
    if (key == 'color') then
        currentconfig.SHIELDS.COLOR = value
        self.UI.setAttribute(id, 'text', value)
    end
    if (key == 'uiheight') then
        local n = tonumber(value)
        if (n ~= nil) then
            currentconfig.SHIELDS.UIHEIGHT = n
            self.UI.setAttribute(id, 'text', n)
        else
            self.UI.setAttribute(id, 'text', value)
        end
    end
    if (key == 'critcolor') then
        currentconfig.SHIELDS.CRITCOLOR = value
        self.UI.setAttribute(id, 'text', value)
    end
    if (key == 'automode') then
        currentconfig.SHIELDS.AUTOMODE = not(currentconfig.SHIELDS.AUTOMODE)
        if (currentconfig.SHIELDS.AUTOMODE) then
            self.UI.setAttribute(id, 'image', 'ui_checkon')
        else
            self.UI.setAttribute(id, 'image', 'ui_checkoff')
        end
    end
end


--MOVEMENT

function ui_movemode(player, val)
    currentconfig.MOVEMENT.MODE = tonumber(val) or 0
    rebuildUI()
end

function ui_editmovement(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'uioffset') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.MOVEMENT.UIOFFSET = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'landshow') then
        local n = value == 'True'
        currentconfig.MOVEMENT.LANDSHOW = n
        self.UI.setAttribute(id, 'isOn', n)
    elseif (key == 'landtest') then
        local n = value == 'True'
        currentconfig.MOVEMENT.LANDTEST = n
        self.UI.setAttribute(id, 'isOn', n)
    elseif (key == 'speeddistance') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.MOVEMENT.SPEEDDISTANCE = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'speedmin') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.MOVEMENT.SPEEDMIN = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'speedmax') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.MOVEMENT.SPEEDMAX = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'turnnotch') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.MOVEMENT.TURNNOTCH = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'turnmax') then
        local n = tonumber(value)
        if (n ~= nil) then currentconfig.MOVEMENT.TURNMAX = n end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'color') then
        currentconfig.MOVEMENT.COLOR = value
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'origin') then
        local dir = string.upper(args[4])
        currentconfig.MOVEMENT.ORIGIN = dir or 'CENTER'
        if (dir == 'CENTER') then
            self.UI.setAttribute('inp_move_origin_edge', 'isOn', false)
            self.UI.setAttribute('inp_move_origin_center', 'isOn', true)
        else
            self.UI.setAttribute('inp_move_origin_edge', 'isOn', true)
            self.UI.setAttribute('inp_move_origin_center', 'isOn', false)
        end
    end
end

function ui_movement_setcolor(player, value, id)
    ui_editmovement(player, value, 'inp_move_color')
end

function ui_movesegments_editsegment(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'distance') then
        local index = tonumber(args[4])
        local n = tonumber(value)
        if (n ~= nil) then
            currentconfig.MOVEMENT.SEGMENTS[index][1] = n
        end
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'remsegment') then
        local index = tonumber(args[4])
        local tmp = {}
        for i,v in pairs(currentconfig.MOVEMENT.SEGMENTS) do
            if (index ~= i) then
                table.insert(tmp, v)
            end
        end
        currentconfig.MOVEMENT.SEGMENTS = tmp
        rebuildUI()
    elseif (key == 'addsegment') then
        local max = currentconfig.MOVEMENT.SEGMENTS[#currentconfig.MOVEMENT.SEGMENTS][1]
        table.insert(currentconfig.MOVEMENT.SEGMENTS, {
            max+1,{}
        })
        rebuildUI()
    end


end



function ui_movesegments_editnotch(player, value, id)

    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'addnotch') then
        local index = tonumber(args[4])
        table.insert(currentconfig.MOVEMENT.SEGMENTS[index][2], 0)
        rebuildUI()
    elseif (key == 'remnotch') then
        local index = tonumber(args[4])
        local tmp = {}
        local cnt = #currentconfig.MOVEMENT.SEGMENTS[index][2]
        for i,v in pairs(currentconfig.MOVEMENT.SEGMENTS[index][2]) do
            if (i ~= cnt) then
                table.insert(tmp, v)
            end
        end
        currentconfig.MOVEMENT.SEGMENTS[index][2] = tmp
        rebuildUI()
    elseif (key == 'editnotch') then
        local index = tonumber(args[4])
        local notch = tonumber(args[5])
        local n = tonumber(value)
        if (n ~= nil) then
            currentconfig.MOVEMENT.SEGMENTS[index][2][notch] = n
        end
        self.UI.setAttribute(id, 'text', value)
    end
end

function ui_movedefinitions(player, value, id)
    local args = {}
    for a in string.gmatch(id, '([^%_]+)') do
        table.insert(args,a)
    end
    local key = args[3]
    if (key == 'name') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][1] = value
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'url') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][2] = value
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'iconx') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][3] = tonumber(value) or 0
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'icony') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][4] = tonumber(value) or 0
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'posx') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][5] = tonumber(value) or 0
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'posy') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][6] = tonumber(value) or 0
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'rot') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][7] = tonumber(value) or 0
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'color') then
        local index = tonumber(args[4])
        currentconfig.MOVEMENT.DEFINITIONS[index][8] = value
        self.UI.setAttribute(id, 'text', value)
    elseif (key == 'adddef') then
        table.insert(currentconfig.MOVEMENT.DEFINITIONS, { '','',0,0,0,0,0,'#ffffff' })
        rebuildUI()
    elseif (key == 'remdef') then
        local index = tonumber(args[4])
        local tmp = {}
        for i,v in pairs(currentconfig.MOVEMENT.DEFINITIONS) do
            if (i ~= index) then
                table.insert(tmp, v)
            end
        end
        currentconfig.MOVEMENT.DEFINITIONS = tmp
        rebuildUI()
    end
end



function ui_meta_toggle(player, value, id)
    local n = metaconfig[value]
    metaconfig[value] = not(n)
    if (metaconfig[value]) then
        self.UI.setAttribute(id, 'image', 'ui_checkon')
    else
        self.UI.setAttribute(id, 'image', 'ui_checkoff')
    end
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

        local basePanel = {tag='HorizontalLayout', attributes={spacing='5', flexibleHeight=0}, children={

            {tag='VerticalLayout', attributes={color='black', padding='5 5 5 5', spacing='5', flexibleWidth=0, childForceExpandHeight=false, preferredWidth=10}, children={
                {tag='Text', attributes={text='Base Width'}},
                {tag='InputField', attributes={id='inp_basic_basewidth', text=currentconfig.BASE_WIDTH, onEndEdit='ui_editbasic', characterValidation='Decimal'}},
                {tag='Text', attributes={text='Base Length'}},
                {tag='InputField', attributes={id='inp_basic_baselength', text=currentconfig.BASE_LENGTH, onEndEdit='ui_editbasic', characterValidation='Decimal'}},
            }},

            {tag='VerticalLayout', attributes={color='black', padding='5 5 5 5', spacing='5', flexibleWidth=0, childForceExpandHeight=false, preferredWidth=10}, children={
                {tag='Text', attributes={text='Scale Factor'}},
                {tag='InputField', attributes={id='inp_basic_scale', text=currentconfig.UI_SCALE, onEndEdit='ui_editbasic', characterValidation='Decimal'}},
                {tag='Text', attributes={text='Refresh Delay'}},
                {tag='InputField', attributes={id='inp_basic_refresh', text=currentconfig.REFRESH, onEndEdit='ui_editbasic', characterValidation='Integer'}},
            }},

            {tag='VerticalLayout', attributes={color='black', padding='5 5 5 5', spacing='5', flexibleWidth=0, childForceExpandHeight=false, preferredWidth=10}, children={
                {tag='Text', attributes={text='Vertical Offset'}},
                {tag='InputField', attributes={id='inp_overhead_height', text=currentconfig.OVERHEAD_HEIGHT, onEndEdit='ui_editoverhead', characterValidation='Decimal'}},
                {tag='Text', attributes={text='Back/Front Offset'}},
                {tag='InputField', attributes={id='inp_overhead_offset', text=currentconfig.OVERHEAD_OFFSET, onEndEdit='ui_editoverhead', characterValidation='Decimal'}},
            }},

            {tag='VerticalLayout', attributes={color='black', padding='5 5 5 5', spacing='5', flexibleWidth=0, childForceExpandHeight=false, preferredWidth=10}, children={
                {tag='Text', attributes={text='Overhead Width'}},
                {tag='InputField', attributes={id='inp_overhead_width', text=currentconfig.OVERHEAD_WIDTH, onEndEdit='ui_editoverhead', characterValidation='Decimal'}},

                {tag='Text', attributes={text='Orientation'}},
                {tag='HorizontalLayout', attributes={}, children={
                    {tag='ToggleButton', attributes={id='inp_overhead_orient_horizontal', onClick='ui_editoverhead(HORIZONTAL)', text='Horizontal', isOn=(currentconfig.OVERHEAD_ORIENT == 'HORIZONTAL')}},
                    {tag='ToggleButton', attributes={id='inp_overhead_orient_vertical', onClick='ui_editoverhead(VERTICAL)', text='Vertical', isOn=(currentconfig.OVERHEAD_ORIENT == 'VERTICAL')}},
                }}
            }},


        }}

        local presetPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Presets', onClick='ui_togglesection(presets)'}},
        }}
        if (sectionVis.presets) then
            local list = {}
            for i,preset in pairs(currentpresets) do
                table.insert(list, {tag='HorizontalLayout', attributes={spacing = '5', height='40', childForceExpandWidth=false, }, children={
                    {tag='InputField', attributes={onEndEdit='ui_presetrename', flexibleWidth=1, id='inp_preset_'..i..'_name', text=preset.name, preferredWidth='500'}},
                    {tag='Button', attributes={onClick='ui_presetsave('..i..')', flexibleWidth=0, preferredWidth='40', image='ui_save'}},
                    {tag='Button', attributes={onClick='ui_presetload('..i..')', flexibleWidth=0, preferredWidth='40', image='ui_load'}},
                    {tag='Button', attributes={onClick='ui_presetdelete('..i..')', flexibleWidth=0, preferredWidth='40', image='ui_close'}},
                }})
            end
            presetPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Presets', onClick='ui_togglesection(presets)'}},
                {tag='VerticalLayout', attributes={}, children=list},
                {tag='HorizontalLayout', attributes={childForceExpandWidth='false', childAlignment='MiddleRight'}, children={
                    {tag='Button', attributes={text='Add Preset', onClick='ui_presetadd', flexibleWidth='0', preferredWidth='100'}},
                }},
            }}
        end
        --PERMISSIONS
        local permViewPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Read Permissions', onClick='ui_togglesection(view_permissions)'}},
        }}
        if (sectionVis.view_permissions) then
            permViewPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Read Permissions', onClick='ui_togglesection(view_permissions)'}},
                {tag='VerticalLayout', children={
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='By Level', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Toggle', attributes={text='Spectator', id='inp_perm_view_'..users.Grey, onClick='ui_permview_toggle('..users.Grey..')', isOn=(bit32.band(users.Grey, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Host', id='inp_perm_view_'..users.Host, onClick='ui_permview_toggle('..users.Host..')', isOn=(bit32.band(users.Host, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Promoted', id='inp_perm_view_'..users.Admin, onClick='ui_permview_toggle('..users.Admin..')', isOn=(bit32.band(users.Admin, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='GameMaster', id='inp_perm_view_'..users.Black, onClick='ui_permview_toggle('..users.Black..')', isOn=(bit32.band(users.Black, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                    }},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='By Color', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Toggle', attributes={text=' ', colors='White|White|#808080|#40404040', id='inp_perm_view_'..users.White, onClick='ui_permview_toggle('..users.White..')', isOn=(bit32.band(users.White, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Brown|Brown|#808080|#40404040', id='inp_perm_view_'..users.Brown, onClick='ui_permview_toggle('..users.Brown..')', isOn=(bit32.band(users.Brown, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Red|Red|#808080|#40404040', id='inp_perm_view_'..users.Red, onClick='ui_permview_toggle('..users.Red..')', isOn=(bit32.band(users.Red, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Orange|Orange|#808080|#40404040', id='inp_perm_view_'..users.Orange, onClick='ui_permview_toggle('..users.Orange..')', isOn=(bit32.band(users.Orange, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Yellow|Yellow|#808080|#40404040', id='inp_perm_view_'..users.Yellow, onClick='ui_permview_toggle('..users.Yellow..')', isOn=(bit32.band(users.Yellow, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Green|Green|#808080|#40404040', id='inp_perm_view_'..users.Green, onClick='ui_permview_toggle('..users.Green..')', isOn=(bit32.band(users.Green, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Teal|Teal|#808080|#40404040', id='inp_perm_view_'..users.Teal, onClick='ui_permview_toggle('..users.Teal..')', isOn=(bit32.band(users.Teal, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Blue|Blue|#808080|#40404040', id='inp_perm_view_'..users.Blue, onClick='ui_permview_toggle('..users.Blue..')', isOn=(bit32.band(users.Blue, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Purple|Purple|#808080|#40404040', id='inp_perm_view_'..users.Purple, onClick='ui_permview_toggle('..users.Purple..')', isOn=(bit32.band(users.Purple, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Pink|Pink|#808080|#40404040', id='inp_perm_view_'..users.Pink, onClick='ui_permview_toggle('..users.Pink..')', isOn=(bit32.band(users.Pink, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                    }},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='By Team', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Toggle', attributes={text='Clubs', id='inp_perm_view_'..users.Clubs, onClick='ui_permview_toggle('..users.Clubs..')', isOn=(bit32.band(users.Clubs, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Diamonds', id='inp_perm_view_'..users.Diamonds, onClick='ui_permview_toggle('..users.Diamonds..')', isOn=(bit32.band(users.Diamonds, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Hearts', id='inp_perm_view_'..users.Hearts, onClick='ui_permview_toggle('..users.Hearts..')', isOn=(bit32.band(users.Hearts, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Spades', id='inp_perm_view_'..users.Spades, onClick='ui_permview_toggle('..users.Spades..')', isOn=(bit32.band(users.Spades, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Jokers', id='inp_perm_view_'..users.Jokers, onClick='ui_permview_toggle('..users.Jokers..')', isOn=(bit32.band(users.Jokers, currentconfig.PERMVIEW) ~= 0), flexibleWidth='1'}},
                    }},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='Presets', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Button', attributes={text='None', onClick='ui_permview_set(0)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='Seated', onClick='ui_permview_set(16376)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='Players', onClick='ui_permview_set(16368)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='All', onClick='ui_permview_set(524287)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='Default', onClick='ui_permview_set(524287)', flexibleWidth='1'}},
                    }},
                }}
            }}
        end

        local permEditPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Edit Permissions', onClick='ui_togglesection(edit_permissions)'}},
        }}
        if (sectionVis.edit_permissions) then
            permEditPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='Text', attributes={alignment='UpperMiddle', fontSize='24', text='Edit Permissions', onClick='ui_togglesection(edit_permissions)'}},
                {tag='VerticalLayout', children={
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='By Level', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Toggle', attributes={text='Spectator', id='inp_perm_edit_'..users.Grey, onClick='ui_permedit_toggle('..users.Grey..')', isOn=(bit32.band(users.Grey, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Host', id='inp_perm_edit_'..users.Host, onClick='ui_permedit_toggle('..users.Host..')', isOn=(bit32.band(users.Host, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Promoted', id='inp_perm_edit_'..users.Admin, onClick='ui_permedit_toggle('..users.Admin..')', isOn=(bit32.band(users.Admin, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='GameMaster', id='inp_perm_edit_'..users.Black, onClick='ui_permedit_toggle('..users.Black..')', isOn=(bit32.band(users.Black, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                    }},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='By Color', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Toggle', attributes={text=' ', colors='White|White|#808080|#40404040', id='inp_perm_edit_'..users.White, onClick='ui_permedit_toggle('..users.White..')', isOn=(bit32.band(users.White, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Brown|Brown|#808080|#40404040', id='inp_perm_edit_'..users.Brown, onClick='ui_permedit_toggle('..users.Brown..')', isOn=(bit32.band(users.Brown, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Red|Red|#808080|#40404040', id='inp_perm_edit_'..users.Red, onClick='ui_permedit_toggle('..users.Red..')', isOn=(bit32.band(users.Red, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Orange|Orange|#808080|#40404040', id='inp_perm_edit_'..users.Orange, onClick='ui_permedit_toggle('..users.Orange..')', isOn=(bit32.band(users.Orange, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Yellow|Yellow|#808080|#40404040', id='inp_perm_edit_'..users.Yellow, onClick='ui_permedit_toggle('..users.Yellow..')', isOn=(bit32.band(users.Yellow, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Green|Green|#808080|#40404040', id='inp_perm_edit_'..users.Green, onClick='ui_permedit_toggle('..users.Green..')', isOn=(bit32.band(users.Green, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Teal|Teal|#808080|#40404040', id='inp_perm_edit_'..users.Teal, onClick='ui_permedit_toggle('..users.Teal..')', isOn=(bit32.band(users.Teal, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Blue|Blue|#808080|#40404040', id='inp_perm_edit_'..users.Blue, onClick='ui_permedit_toggle('..users.Blue..')', isOn=(bit32.band(users.Blue, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Purple|Purple|#808080|#40404040', id='inp_perm_edit_'..users.Purple, onClick='ui_permedit_toggle('..users.Purple..')', isOn=(bit32.band(users.Purple, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text=' ', colors='Pink|Pink|#808080|#40404040', id='inp_perm_edit_'..users.Pink, onClick='ui_permedit_toggle('..users.Pink..')', isOn=(bit32.band(users.Pink, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                    }},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='By Team', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Toggle', attributes={text='Clubs', id='inp_perm_edit_'..users.Clubs, onClick='ui_permedit_toggle('..users.Clubs..')', isOn=(bit32.band(users.Clubs, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Diamonds', id='inp_perm_edit_'..users.Diamonds, onClick='ui_permedit_toggle('..users.Diamonds..')', isOn=(bit32.band(users.Diamonds, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Hearts', id='inp_perm_edit_'..users.Hearts, onClick='ui_permedit_toggle('..users.Hearts..')', isOn=(bit32.band(users.Hearts, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Spades', id='inp_perm_edit_'..users.Spades, onClick='ui_permedit_toggle('..users.Spades..')', isOn=(bit32.band(users.Spades, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                        {tag='Toggle', attributes={text='Jokers', id='inp_perm_edit_'..users.Jokers, onClick='ui_permedit_toggle('..users.Jokers..')', isOn=(bit32.band(users.Jokers, currentconfig.PERMEDIT) ~= 0), flexibleWidth='1'}},
                    }},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false'}, children={
                        {tag='Text', attributes={text='Presets', preferredHeight='40', preferredWidth='100', flexibleWidth='0'}},
                        {tag='Button', attributes={text='None', onClick='ui_permedit_set(0)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='Seated', onClick='ui_permedit_set(16376)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='Players', onClick='ui_permedit_set(16368)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='All', onClick='ui_permedit_set(524287)', flexibleWidth='1'}},
                        {tag='Button', attributes={text='Default', onClick='ui_permedit_set(10)', flexibleWidth='1'}},
                    }},
                }}
            }}
        end

        --BARS
        local barsPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Bars', onClick='ui_togglesection(bars)'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_BARS and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(BARS)', id='module_toggle_bars'}},
            }},
        }}
        if (sectionVis.bars) then
            local barList = {
                {tag='Row', attributes={preferredHeight=30}, children={
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text='Name'}}}},
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text='Color'}}}},
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text='Current'}}}},
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text='Max'}}}},
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text='Text'}}}},
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text='Big'}}}},
                    {tag='Cell', children={{tag='Text', attributes={alignment='LowerCenter', text=''}}}},
                }}
            }
            for i,bar in pairs(currentconfig.BARS) do
                table.insert(barList, {tag='Row', attributes={preferredHeight=40}, children={
                    {tag='Cell', children={{tag='InputField', attributes={id='inp_bar_'..i..'_name', text=bar[1], onEndEdit='ui_editbar'}}}},
                    {tag='Cell', children={{tag='InputField', attributes={id='inp_bar_'..i..'_color', text=bar[2], onEndEdit='ui_editbar'}}}},
                    {tag='Cell', children={{tag='InputField', attributes={id='inp_bar_'..i..'_current', text=bar[3], onEndEdit='ui_editbar', characterValidation='Integer'}}}},
                    {tag='Cell', children={{tag='InputField', attributes={id='inp_bar_'..i..'_maximum', text=bar[4], onEndEdit='ui_editbar', characterValidation='Integer'}}}},
                    {tag='Cell', children={{tag='Button', attributes={width=20, id='inp_bar_'..i..'_text', image=(bar[5] and 'ui_checkon' or 'ui_checkoff'), onClick='ui_editbar'}}}},
                    {tag='Cell', children={{tag='Button', attributes={width=20, id='inp_bar_'..i..'_big', image=(bar[6] and 'ui_checkon' or 'ui_checkoff'), onClick='ui_editbar'}}}},
                    {tag='Cell', children={{tag='Button', attributes={width=20, image='ui_close', onClick='ui_rembar('..i..')'}}}},
                }})
            end
            barsPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Bars', onClick='ui_togglesection(bars)'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_BARS and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(BARS)', id='module_toggle_bars'}},
                }},
                {tag='TableLayout', attributes={columnWidths = '0 180 100 100 40 40 40', preferredHeight = ((#(currentconfig.BARS)+1) * 40)}, children=barList},
                {tag='HorizontalLayout', attributes={childForceExpandWidth='false', childAlignment='MiddleRight'}, children={
                    {tag='Button', attributes={text='Add Bar', onClick='ui_addbar', flexibleWidth='0', preferredWidth='100'}},
                }},
            }}
        end

        --MARKERS
        local markersPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Markers', onClick='ui_togglesection(markers)'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_MARKERS and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(MARKERS)', id='module_toggle_markers'}},
            }},
        }}
        if (sectionVis.markers) then
            markersPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Markers', onClick='ui_togglesection(markers)'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_MARKERS and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(MARKERS)', id='module_toggle_markers'}},
                }},
                {tag='Text', attributes={text='There are no additional parameters for this module.'}},
            }}
        end

        --ARCS
        local arcPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Arcs', onClick='ui_togglesection(arcs)'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_ARC and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(ARC)', id='module_toggle_arc'}},
            }},
        }}

        if (sectionVis.arcs) then
            local arcOptions = {}
            local arcButtons = {}

            table.insert(arcButtons, {tag='ToggleButton', attributes={id='inp_arc_shape_-1', onClick='ui_editarc_shape(-1)', preferredWidth=40, isOn = currentconfig.ARCS.SHAPE == -1, image='ui_help_outline'}})
            for i,shape in pairs(arcshapes) do
                table.insert(arcButtons, {tag='ToggleButton', attributes={id='inp_arc_shape_'..i, onClick='ui_editarc_shape('..i..')', preferredWidth=40, isOn = currentconfig.ARCS.SHAPE == i, image='arc_'..shape}})
            end


            if (currentconfig.ARCS.MODE == const.INCREMENTAL) then
                arcOptions = {  tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='HorizontalLayout', attributes={spacing='5', childForceExpandWidth=false}, children=arcButtons},
                        {tag='VerticalLayout', attributes={id='cnt_arc_mesh', flexibleWidth=0, preferredWidth=400, active=(currentconfig.ARCS.SHAPE == -1)}, children={
                            {tag='Text', attributes={text='Arc Model URL'}},
                            {tag='InputField', attributes={id='inp_arc_mesh', text=currentconfig.ARCS.MESH, onEndEdit='ui_editarc'}},
                        }},
                    }},
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=300}, children={
                            {tag='Text', attributes={text='Scale Factor'}},
                            {tag='InputField', attributes={id='inp_arc_scale', text=currentconfig.ARCS.SCALE, onEndEdit='ui_editarc', characterValidation='Decimal'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=300}, children={
                            {tag='Text', attributes={text='Max Increments'}},
                            {tag='InputField', attributes={id='inp_arc_max', text=currentconfig.ARCS.MAX, onEndEdit='ui_editarc', characterValidation='Integer'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=300}, children={
                            {tag='Text', attributes={text='\'Zero\' radius'}},
                            {tag='InputField', attributes={id='inp_arc_zero', text=currentconfig.ARCS.ZERO, onEndEdit='ui_editarc', characterValidation='Decimal'}},
                        }},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Color'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                            {tag='InputField', attributes={id='inp_arc_color', text=currentconfig.ARCS.COLOR or 'inherit', flexibleWidth='1', onEndEdit='ui_editarc'}},
                            {tag='Button', attributes={image='ui_share', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_arc_setcolor(inherit)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_arc_setcolor(#ffffff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_arc_setcolor(#713b17)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_arc_setcolor(#da1918)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_arc_setcolor(#f4641d)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_arc_setcolor(#e7e52c)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_arc_setcolor(#31b32b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_arc_setcolor(#21b19b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_arc_setcolor(#1f87ff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_arc_setcolor(#a020f0)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_arc_setcolor(#f570ce)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_arc_setcolor(#191919)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_arc_setcolor(#aaaaaa)'}},
                        }}
                    }},
                }}
            elseif (currentconfig.ARCS.MODE == const.STATIC) then
                arcOptions = { tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='HorizontalLayout', attributes={spacing='5', childForceExpandWidth=false}, children=arcButtons},
                        {tag='VerticalLayout', attributes={id='cnt_arc_mesh', flexibleWidth=0, preferredWidth=400, active=(currentconfig.ARCS.SHAPE == -1)}, children={
                            {tag='Text', attributes={text='Arc Model URL'}},
                            {tag='InputField', attributes={id='inp_arc_mesh', text=currentconfig.ARCS.MESH, onEndEdit='ui_editarc'}},
                        }},
                    }},
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=300}, children={
                            {tag='Text', attributes={text='Scale Factor'}},
                            {tag='InputField', attributes={id='inp_arc_scale', text=currentconfig.ARCS.SCALE, onEndEdit='ui_editarc', characterValidation='Decimal'}},
                        }},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Color'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                            {tag='InputField', attributes={id='inp_arc_color', text=currentconfig.ARCS.COLOR or 'inherit', flexibleWidth='1', onEndEdit='ui_editarc'}},
                            {tag='Button', attributes={image='ui_share', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_arc_setcolor(inherit)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_arc_setcolor(#ffffff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_arc_setcolor(#713b17)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_arc_setcolor(#da1918)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_arc_setcolor(#f4641d)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_arc_setcolor(#e7e52c)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_arc_setcolor(#31b32b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_arc_setcolor(#21b19b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_arc_setcolor(#1f87ff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_arc_setcolor(#a020f0)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_arc_setcolor(#f570ce)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_arc_setcolor(#191919)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_arc_setcolor(#aaaaaa)'}},
                        }}
                    }},
                }}
            elseif (currentconfig.ARCS.MODE == const.BRACKETS) then

                local bracketList = {}

                for i,v in pairs(currentconfig.ARCS.BRACKETS) do
                    table.insert(bracketList, {
                        tag='InputField', attributes={id='inp_arcbracket_'..i, text=v, minWidth='80', onEndEdit='ui_editarcbracket', characterValidation='Integer'}
                    })
                end

                arcOptions = { tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='HorizontalLayout', attributes={spacing='5', childForceExpandWidth=false}, children=arcButtons},
                        {tag='VerticalLayout', attributes={id='cnt_arc_mesh', flexibleWidth=0, preferredWidth=400, active=(currentconfig.ARCS.SHAPE == -1)}, children={
                            {tag='Text', attributes={text='Arc Model URL'}},
                            {tag='InputField', attributes={id='inp_arc_mesh', text=currentconfig.ARCS.MESH, onEndEdit='ui_editarc'}},
                        }},
                    }},
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=300}, children={
                            {tag='Text', attributes={text='Scale Factor'}},
                            {tag='InputField', attributes={id='inp_arc_scale', text=currentconfig.ARCS.SCALE, onEndEdit='ui_editarc', characterValidation='Decimal'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=300}, children={
                            {tag='Text', attributes={text='\'Zero\' radius'}},
                            {tag='InputField', attributes={id='inp_arc_zero', text=currentconfig.ARCS.ZERO, onEndEdit='ui_editarc', characterValidation='Decimal'}},
                        }},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Color'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                            {tag='InputField', attributes={id='inp_arc_color', text=currentconfig.ARCS.COLOR or 'inherit', flexibleWidth='1', onEndEdit='ui_editarc'}},
                            {tag='Button', attributes={image='ui_share', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_arc_setcolor(inherit)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_arc_setcolor(#ffffff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_arc_setcolor(#713b17)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_arc_setcolor(#da1918)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_arc_setcolor(#f4641d)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_arc_setcolor(#e7e52c)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_arc_setcolor(#31b32b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_arc_setcolor(#21b19b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_arc_setcolor(#1f87ff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_arc_setcolor(#a020f0)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_arc_setcolor(#f570ce)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_arc_setcolor(#191919)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_arc_setcolor(#aaaaaa)'}},
                        }}
                    }},
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                            {tag='Text', attributes={text='Brackets'}},
                            {tag='HorizontalLayout', attributes={childForceExpandWidth=false, childForceExpandHeight=false, childAlignment='MiddleLeft'}, children={
                                {tag='HorizontalScrollView', attributes={preferredHeight='65', color='transparent', flexibleWidth='1', horizontalScrollbarVisibility='Permanent'}, children={
                                    {tag='HorizontalLayout', attributes={contentSizeFitter = 'horizontal', childForceExpandWidth=false, childForceExpandHeight=false, spacing='5'}, children=bracketList}
                                }},
                                {tag='Button', attributes={flexibleWidth='0', preferredWidth='40', image='ui_plus', onClick='ui_addarcbracket'}},
                                {tag='Button', attributes={flexibleWidth='0', preferredWidth='40', image='ui_minus', onClick='ui_remarcbracket'}},
                            }}
                        }},
                    }},
                }}
            end

            arcPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Arcs', onClick='ui_togglesection(arcs)'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_ARC and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(ARC)', id='module_toggle_arc'}},
                }},
                {tag='Text', attributes={text='Mode'}},
                {tag='HorizontalLayout', attributes={spacing='5'}, children={
                    {tag='ToggleButton', attributes={onClick='ui_arcmode(1)', text='Incremental', isOn=(currentconfig.ARCS.MODE == 1)}},
                    {tag='ToggleButton', attributes={onClick='ui_arcmode(2)', text='Static', isOn=(currentconfig.ARCS.MODE == 2)}},
                    {tag='ToggleButton', attributes={onClick='ui_arcmode(3)', text='Brackets', isOn=(currentconfig.ARCS.MODE == 3)}},
                }},
                arcOptions

            }}
        end

        -- Flags
        local flagPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Flag', onClick='ui_togglesection(flag)'}},
                {tag='Button', attributes={minWidth = 30, image=(currentconfig.LOCK_FLAG and 'ui_locked' or 'ui_unlocked'), onClick='ui_togglelock(FLAG)', id='flag_editability'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_FLAG and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(FLAG)', id='module_toggle_flag'}},
            }},
        }}
        if (sectionVis.flag) then
            flagPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Flag', onClick='ui_togglesection(flag)'}},
                    {tag='Button', attributes={minWidth = 30, image=(currentconfig.LOCK_FLAG and 'ui_locked' or 'ui_unlocked'), onClick='ui_togglelock(FLAG)', id='flag_editability'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_FLAG and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(FLAG)', id='module_toggle_flag'}},
                }},
                {tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                            {tag='Text', attributes={text='Flag Image URL'}},
                            {tag='InputField', attributes={id='inp_flag_image', text=currentconfig.FLAG.IMAGE or '', onEndEdit='ui_editflag'}},
                        }},
                    }},
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=200}, children={
                            {tag='Text', attributes={text='Width'}},
                            {tag='InputField', attributes={id='inp_flag_width', text=currentconfig.FLAG.WIDTH or 0, onEndEdit='ui_editflag', characterValidation='Decimal'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=200}, children={
                            {tag='Text', attributes={text='Height'}},
                            {tag='InputField', attributes={id='inp_flag_height', text=currentconfig.FLAG.HEIGHT or 0, onEndEdit='ui_editflag', characterValidation='Decimal'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=200, childForceExpandWidth = false, childForceExpandHeight = false, childAlignment='MiddleCenter'}, children={
                            {tag='Text', attributes={text='Auto-on'}},
                            {tag='Button', attributes={minWidth=30, preferredHeight=30, preferredWidth=30, image=((currentconfig.FLAG.AUTOMODE or false) and 'ui_checkon' or 'ui_checkoff'), onClick='ui_editflag', id='inp_flag_automode'}},
                        }},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Color'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                            {tag='InputField', attributes={id='inp_flag_color', text=currentconfig.FLAG.COLOR or 'inherit', flexibleWidth='1', onEndEdit='ui_editflag'}},
                            {tag='Button', attributes={image='ui_share', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_flag_setcolor(inherit)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_flag_setcolor(#ffffff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_flag_setcolor(#713b17)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_flag_setcolor(#da1918)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_flag_setcolor(#f4641d)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_flag_setcolor(#e7e52c)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_flag_setcolor(#31b32b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_flag_setcolor(#21b19b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_flag_setcolor(#1f87ff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_flag_setcolor(#a020f0)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_flag_setcolor(#f570ce)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_flag_setcolor(#191919)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_flag_setcolor(#aaaaaa)'}},
                        }}
                    }},
                }}
            }}
        end

        local geometryPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Geometry', onClick='ui_togglesection(geometry)'}},
                {tag='Button', attributes={minWidth = 30, image=(currentconfig.LOCK_GEOMETRY and 'ui_locked' or 'ui_unlocked'), onClick='ui_togglelock(GEOMETRY)', id='geometry_editability'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_GEOMETRY and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(GEOMETRY)', id='module_toggle_geometry'}},
            }},
        }}
        if (sectionVis.geometry) then
            local colorlist = {}

            geometryPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Geometry', onClick='ui_togglesection(geometry)'}},
                    {tag='Button', attributes={minWidth = 30, image=(currentconfig.LOCK_GEOMETRY and 'ui_locked' or 'ui_unlocked'), onClick='ui_togglelock(GEOMETRY)', id='geometry_editability'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_GEOMETRY and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(GEOMETRY)', id='module_toggle_geometry'}},
                }},
                {tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Mesh URL'}},
                        {tag='InputField', attributes={id='inp_geometry_mesh', text=currentconfig.GEOMETRY.MESH or '', onEndEdit='ui_editgeometry'}},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Texture URL'}},
                        {tag='InputField', attributes={id='inp_geometry_texture', text=currentconfig.GEOMETRY.TEXTURE or '', onEndEdit='ui_editgeometry'}},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Normal URL'}},
                        {tag='InputField', attributes={id='inp_geometry_texture', text=currentconfig.GEOMETRY.NORMAL or '', onEndEdit='ui_editgeometry'}},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Material'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5}, children={
                            {tag='ToggleButton', attributes={id='inp_geometry_material_0', isOn=(currentconfig.GEOMETRY.MATERIAL == 0), onClick='ui_editgeometry('..const.PLASTIC..')', text='Plastic'}},
                            {tag='ToggleButton', attributes={id='inp_geometry_material_1', isOn=(currentconfig.GEOMETRY.MATERIAL == 1), onClick='ui_editgeometry('..const.WOOD..')', text='Wood'}},
                            {tag='ToggleButton', attributes={id='inp_geometry_material_2', isOn=(currentconfig.GEOMETRY.MATERIAL == 2), onClick='ui_editgeometry('..const.METAL..')', text='Metal'}},
                            {tag='ToggleButton', attributes={id='inp_geometry_material_3', isOn=(currentconfig.GEOMETRY.MATERIAL == 3), onClick='ui_editgeometry('..const.CARDBOARD..')', text='Cardboard'}},
                        }},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Color'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                            {tag='InputField', attributes={id='inp_geometry_color', text=currentconfig.GEOMETRY.COLOR or 'inherit', flexibleWidth='1', onEndEdit='ui_editgeometry'}},
                            {tag='Button', attributes={image='ui_share', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_geometry_setcolor(inherit)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_geometry_setcolor(#ffffff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_geometry_setcolor(#713b17)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_geometry_setcolor(#da1918)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_geometry_setcolor(#f4641d)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_geometry_setcolor(#e7e52c)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_geometry_setcolor(#31b32b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_geometry_setcolor(#21b19b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_geometry_setcolor(#1f87ff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_geometry_setcolor(#a020f0)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_geometry_setcolor(#f570ce)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_geometry_setcolor(#191919)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_geometry_setcolor(#aaaaaa)'}},
                        }}
                    }},
                }}
            }}
        end

        local movementPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Movement', onClick='ui_togglesection(movement)'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_MOVEMENT and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(MOVEMENT)', id='module_toggle_movement'}},
            }},
        }}
        if (sectionVis.movement) then

            local moveOptions = {}

            if (currentconfig.MOVEMENT.MODE == const.SIMPLEGAUGE) then
                moveOptions = { tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth = 1, childForceExpandHeight=false}, children={
                            {tag='Text', attributes={text='Segment Length'}},
                            {tag='InputField', attributes={id='inp_move_speeddistance', text=currentconfig.MOVEMENT.SPEEDDISTANCE or '1', onEndEdit='ui_editmovement', characterValidation='Decimal'}},
                            {tag='Text', attributes={text='Speed Maximum'}},
                            {tag='InputField', attributes={id='inp_move_speedmax', text=currentconfig.MOVEMENT.SPEEDMAX or '1', onEndEdit='ui_editmovement', characterValidation='Decimal'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth = 1, childForceExpandHeight=false}, children={
                            {tag='Text', attributes={text='Notch Angle'}},
                            {tag='InputField', attributes={id='inp_move_turnnotch', text=currentconfig.MOVEMENT.TURNNOTCH or '22.5', onEndEdit='ui_editmovement', characterValidation='Decimal'}},
                            {tag='Text', attributes={text='Notch Limit'}},
                            {tag='InputField', attributes={id='inp_move_turnmax', text=currentconfig.MOVEMENT.TURNMAX or '4', onEndEdit='ui_editmovement', characterValidation='Integer'}},
                        }},
                    }}
                }}
            elseif (currentconfig.MOVEMENT.MODE == const.COMPLEXGAUGE) then
                local moveSegments = {}
                for i,v in pairs(currentconfig.MOVEMENT.SEGMENTS) do
                    local notches = {}
                    for j,u in pairs(v[2]) do
                        table.insert(notches, {
                            tag='InputField',
                            attributes={
                                preferredWidth='100',
                                flexibleWidth=0,
                                id='inp_movesegments_editnotch_'..i..'_'..j,
                                text=v[2][j],
                                onEndEdit = 'ui_movesegments_editnotch'
                            }
                        })
                    end
                    table.insert(moveSegments, {tag='HorizontalLayout', attributes={childForceExpandWidth=false, childForceExpandHeight=false, minHeight='60', childAlignment='MiddleLeft'}, children={
                        {tag='InputField', attributes={id='inp_movesegments_distance_'..i, onEndEdit = 'ui_movesegments_editsegment', text=v[1], flexibleWidth = 1, preferredWidth = 40, interactable=(i ~= 1)}},
                        {tag='Button', attributes={id='btn_movesegments_remsegment_'..i, onClick='ui_movesegments_editsegment', image='ui_close', interactable=(i ~= 1), flexibleWidth = 0, preferredWidth = 40}},
                        {tag='HorizontalScrollView', attributes={flexibleWidth = 1, preferredWidth = 400, childForceExpandWidth=false, flexibleHeight=1}, children={
                            {tag='HorizontalLayout', attributes={childForceExpandWidth=false, contentSizeFitter='horizontal'}, children=notches}
                        }},
                        {tag='Button', attributes={id='btn_movesegments_addnotch_'..i, onClick='ui_movesegments_editnotch', image='ui_plus', active=true, flexibleWidth = 0, preferredWidth = 40}},
                        {tag='Button', attributes={id='btn_movesegments_remnotch_'..i, onClick='ui_movesegments_editnotch', image='ui_minus', interactable=(#v[2] > 0), flexibleWidth = 0, preferredWidth = 40}},
                    }})
                end
                moveOptions = { tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='Text', attributes={text='Segments'}},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false', childAlignment='MiddleLeft'}, children={
                        {tag='Text', attributes={text='Distance', flexibleWidth=1, preferredWidth=40}},
                        {tag='Text', attributes={text='', flexibleWidth=0, preferredWidth=40}},
                        {tag='Text', attributes={text='Notches (in Degrees)', flexibleWidth=1, preferredWidth=400}},
                        {tag='Text', attributes={text='', flexibleWidth=0, preferredWidth=40}},
                        {tag='Text', attributes={text='', flexibleWidth=0, preferredWidth=40}},
                    }},
                    {tag='VerticalLayout', attributes={contentSizeFitter='vertical'}, children=moveSegments},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false', childAlignment='MiddleRight'}, children={
                        {tag='Button', attributes={id='btn_movesegments_addsegment', text='Add Segment', onClick='ui_movesegments_editsegment', flexibleWidth='0', preferredWidth='200'}},
                    }},
                }}
            elseif (currentconfig.MOVEMENT.MODE == const.RADIUS) then
                moveOptions = { tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='HorizontalLayout', attributes={spacing='5'}, children={
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth = 1, childForceExpandHeight=false}, children={
                            {tag='Text', attributes={text='Speed Minimum'}},
                            {tag='InputField', attributes={id='inp_move_speedmin', text=currentconfig.MOVEMENT.SPEEDMIN or '0', onEndEdit='ui_editmovement', characterValidation='Decimal'}},
                            {tag='Text', attributes={text='Speed Maximum'}},
                            {tag='InputField', attributes={id='inp_move_speedmax', text=currentconfig.MOVEMENT.SPEEDMAX or '1', onEndEdit='ui_editmovement', characterValidation='Decimal'}},
                        }},
                        {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth = 1, childForceExpandHeight=false}, children={
                            {tag='Text', attributes={text='Origin'}},
                            {tag='HorizontalLayout', attributes={spacing='5'}, children={
                                {tag='ToggleButton', attributes={id='inp_move_origin_center', onClick='ui_editmovement(CENTER)', text='Center', isOn=(currentconfig.MOVEMENT.ORIGIN == 'CENTER')}},
                                {tag='ToggleButton', attributes={id='inp_move_origin_edge', onClick='ui_editmovement(EDGE)', text='Edge', isOn=(currentconfig.MOVEMENT.ORIGIN == 'EDGE')}},
                            }},
                        }},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=400}, children={
                        {tag='Text', attributes={text='Color'}},
                        {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                            {tag='InputField', attributes={id='inp_move_color', text=currentconfig.MOVEMENT.COLOR or 'inherit', flexibleWidth='1', onEndEdit='ui_editmovement'}},
                            {tag='Button', attributes={image='ui_share', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_movement_setcolor(inherit)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_movement_setcolor(#ffffff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_movement_setcolor(#713b17)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_movement_setcolor(#da1918)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_movement_setcolor(#f4641d)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_movement_setcolor(#e7e52c)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_movement_setcolor(#31b32b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_movement_setcolor(#21b19b)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_movement_setcolor(#1f87ff)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_movement_setcolor(#a020f0)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_movement_setcolor(#f570ce)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_movement_setcolor(#191919)'}},
                            {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_movement_setcolor(#aaaaaa)'}},
                        }}
                    }},
                }}
            elseif (currentconfig.MOVEMENT.MODE == const.DEFINED) then
                local moveDefinitions = {
                    {tag='Row', attributes={}, children={
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='Name'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='Icon URL'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='iconX'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='iconY'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='posX'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='posY'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='rot'}}}},
                        {tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='color'}}}},
                        --{tag='Cell', children={{tag='Text', attributes={alignment='MiddleCenter', text='', image='ui_close'}}}},
                    }},
                }
                for i,v in pairs(currentconfig.MOVEMENT.DEFINITIONS) do
                    table.insert(moveDefinitions, {tag='Row', attributes={}, children={
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_name_'..i, onEndEdit = 'ui_movedefinitions', text=v[1]}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_url_'..i, onEndEdit = 'ui_movedefinitions', text=v[2]}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_iconx_'..i, onEndEdit = 'ui_movedefinitions', text=v[3], characterValidation='Integer'}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_icony_'..i, onEndEdit = 'ui_movedefinitions', text=v[4], characterValidation='Integer'}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_posx_'..i, onEndEdit = 'ui_movedefinitions', text=v[5], characterValidation='Decimal'}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_posy_'..i, onEndEdit = 'ui_movedefinitions', text=v[6], characterValidation='Decimal'}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_rot_'..i, onEndEdit = 'ui_movedefinitions', text=v[7], characterValidation='Decimal'}}}},
                        {tag='Cell', children={{tag='InputField', attributes={fontSize='18', id='inp_movedefinitions_color_'..i, onEndEdit = 'ui_movedefinitions', text=v[8]}}}},
                        {tag='Cell', children={{tag='Button', attributes={id='inp_movedefinitions_remdef_'..i, onClick='ui_movedefinitions', image='ui_close', interactable=(i ~= 1)}}}},
                    }})
                end
                moveOptions = { tag='VerticalLayout', attributes={color='#404040', padding='10 10 10 10', spacing='5', childForceExpandHeight=false}, children={
                    {tag='Text', attributes={text='Definitions'}},
                    {tag='TableLayout', attributes={columnWidths = '120 0 60 60 60 60 60 100 40', preferredHeight = ((#(currentconfig.MOVEMENT.DEFINITIONS)+1) * 36)}, children=moveDefinitions},
                    {tag='HorizontalLayout', attributes={childForceExpandWidth='false', childAlignment='MiddleRight'}, children={
                        {tag='Button', attributes={id='inp_movedefinitions_adddef', text='Add Definition', onClick='ui_movedefinitions', flexibleWidth='0', preferredWidth='200'}},
                    }},
                }}
            end
            movementPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Movement', onClick='ui_togglesection(movement)'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_MOVEMENT and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(MOVEMENT)', id='module_toggle_movement'}},
                }},
                {tag='HorizontalLayout', attributes={spacing='5', childForceExpandHeight=false}, children={
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth = 1, childForceExpandHeight=false}, children={
                        {tag='Text', attributes={text='UI Offset'}},
                        {tag='InputField', attributes={id='inp_move_uioffset', text=currentconfig.MOVEMENT.UIOFFSET or '0.25', onEndEdit='ui_editmovement', characterValidation='Decimal'}},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth = 1, childForceExpandHeight=false}, children={
                        {tag='Text', attributes={text='Landing Area'}},
                        {tag='HorizontalLayout', attributes={spacing='5'}, children={
                            {tag='Toggle', attributes={id='inp_move_landshow', text='Show', isOn=currentconfig.MOVEMENT.LANDSHOW or false, onValueChanged='ui_editmovement'}},
                            {tag='Toggle', attributes={id='inp_move_landtest', text='Collision Check', isOn=currentconfig.MOVEMENT.LANDTEST or false, onValueChanged='ui_editmovement'}},
                        }}
                    }},
                }},
                {tag='Text', attributes={text='Mode'}},
                {tag='HorizontalLayout', attributes={spacing='5'}, children={
                    {tag='ToggleButton', attributes={onClick='ui_movemode('..const.SIMPLEGAUGE..')', text='Simple Gauge', isOn=(currentconfig.MOVEMENT.MODE == const.SIMPLEGAUGE)}},
                    {tag='ToggleButton', attributes={onClick='ui_movemode('..const.RADIUS..')', text='Radius Limit', isOn=(currentconfig.MOVEMENT.MODE == const.RADIUS)}},
                    {tag='ToggleButton', attributes={onClick='ui_movemode('..const.COMPLEXGAUGE..')', text='Complex Gauge', isOn=(currentconfig.MOVEMENT.MODE == const.COMPLEXGAUGE)}},
                    {tag='ToggleButton', attributes={onClick='ui_movemode('..const.DEFINED..')', text='Pre-Defined', isOn=(currentconfig.MOVEMENT.MODE == const.DEFINED)}},
                }},
                moveOptions
            }}
        end


        local shieldsPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
            {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Shields', onClick='ui_togglesection(shields)'}},
                {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_SHIELDS and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(SHIELDS)', id='module_toggle_shields'}},
            }},
        }}
        if (sectionVis.shields) then
            local shieldsOptions = {}
            if (currentconfig.SHIELDS.SHAPE == const.SHIELD_FRONTBACK) then
                shieldsOptions = {tag='TableLayout', attributes={columnWidths = '0 0 0 0', preferredHeight = '120'}, children = {
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Direction'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Value'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Limit'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Critical'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Front'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_1', text=currentconfig.SHIELDS.CURRENT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_1', text=currentconfig.SHIELDS.LIMIT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_1', text=currentconfig.SHIELDS.CRITICAL[1], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Back'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_2', text=currentconfig.SHIELDS.CURRENT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_2', text=currentconfig.SHIELDS.LIMIT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_2', text=currentconfig.SHIELDS.CRITICAL[2], onEndEdit = 'ui_editshields'}}}},
                    }},
                }}
            end
            if (currentconfig.SHIELDS.SHAPE == const.SHIELD_LEFTRIGHT) then
                shieldsOptions = {tag='TableLayout', attributes={columnWidths = '0 0 0 0', preferredHeight = '120'}, children = {
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Direction'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Value'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Limit'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Critical'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Left'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_1', text=currentconfig.SHIELDS.CURRENT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_1', text=currentconfig.SHIELDS.LIMIT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_1', text=currentconfig.SHIELDS.CRITICAL[1], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Right'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_2', text=currentconfig.SHIELDS.CURRENT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_2', text=currentconfig.SHIELDS.LIMIT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_2', text=currentconfig.SHIELDS.CRITICAL[2], onEndEdit = 'ui_editshields'}}}},
                    }},
                }}
            end
            if (currentconfig.SHIELDS.SHAPE == const.SHIELD_FOURWAY) then
                shieldsOptions = {tag='TableLayout', attributes={columnWidths = '0 0 0 0', preferredHeight = '200'}, children = {
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Direction'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Value'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Limit'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Critical'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Front'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_1', text=currentconfig.SHIELDS.CURRENT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_1', text=currentconfig.SHIELDS.LIMIT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_1', text=currentconfig.SHIELDS.CRITICAL[1], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Left'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_2', text=currentconfig.SHIELDS.CURRENT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_2', text=currentconfig.SHIELDS.LIMIT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_2', text=currentconfig.SHIELDS.CRITICAL[2], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Right'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_3', text=currentconfig.SHIELDS.CURRENT[3], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_3', text=currentconfig.SHIELDS.LIMIT[3], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_3', text=currentconfig.SHIELDS.CRITICAL[3], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Back'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_4', text=currentconfig.SHIELDS.CURRENT[4], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_4', text=currentconfig.SHIELDS.LIMIT[4], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_4', text=currentconfig.SHIELDS.CRITICAL[4], onEndEdit = 'ui_editshields'}}}},
                    }},
                }}
            end
            if (currentconfig.SHIELDS.SHAPE == const.SHIELD_SIXWAY) then
                shieldsOptions = {tag='TableLayout', attributes={columnWidths = '0 0 0 0', preferredHeight = '280'}, children = {
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Direction'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Value'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Limit'}}}},
                        {tag='cell', children={{tag='text', attributes={alignment='MiddleCenter', text='Critical'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Front'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_1', text=currentconfig.SHIELDS.CURRENT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_1', text=currentconfig.SHIELDS.LIMIT[1], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_1', text=currentconfig.SHIELDS.CRITICAL[1], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Front Left'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_2', text=currentconfig.SHIELDS.CURRENT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_2', text=currentconfig.SHIELDS.LIMIT[2], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_2', text=currentconfig.SHIELDS.CRITICAL[2], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Front Right'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_3', text=currentconfig.SHIELDS.CURRENT[3], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_3', text=currentconfig.SHIELDS.LIMIT[3], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_3', text=currentconfig.SHIELDS.CRITICAL[3], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Back Left'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_4', text=currentconfig.SHIELDS.CURRENT[4], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_4', text=currentconfig.SHIELDS.LIMIT[4], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_4', text=currentconfig.SHIELDS.CRITICAL[4], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Back Right'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_5', text=currentconfig.SHIELDS.CURRENT[5], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_5', text=currentconfig.SHIELDS.LIMIT[5], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_5', text=currentconfig.SHIELDS.CRITICAL[5], onEndEdit = 'ui_editshields'}}}},
                    }},
                    {tag='row', children={
                        {tag='cell', children={{tag='text', attributes={text='Back'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_current_6', text=currentconfig.SHIELDS.CURRENT[6], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_limit_6', text=currentconfig.SHIELDS.LIMIT[6], onEndEdit = 'ui_editshields'}}}},
                        {tag='cell', children={{tag='InputField', attributes={id='inp_shields_critical_6', text=currentconfig.SHIELDS.CRITICAL[6], onEndEdit = 'ui_editshields'}}}},
                    }},
                }}
            end

            shieldsPanel = {tag='VerticalLayout', attributes={spacing='5', flexibleHeight=0, padding='5 5 5 5', color='black'}, children={
                {tag='HorizontalLayout', attributes={preferredHeight=30}, children={
                    {tag='Text', attributes={preferredWidth = 1000, alignment='UpperLeft', fontSize='24', text='Module: Shields', onClick='ui_togglesection(shields)'}},
                    {tag='Button', attributes={minWidth=30, preferredWidth=30, image=(currentconfig.MODULE_SHIELDS and 'ui_checkon' or 'ui_checkoff'), onClick='toggle_module(SHIELDS)', id='module_toggle_shields'}},
                }},
                {tag='HorizontalLayout', attributes={spacing='5', childForceExpandHeight=false}, children={
                    {tag='VerticalLayout', attributes={ flexibleWidth=1, preferredWidth = 100, childForceExpandHeight=false}, children={
                        {tag='Text', attributes={text='UI Height'}},
                        {tag='InputField', attributes={id='inp_shields_uiheight', text=currentconfig.SHIELDS.UIHEIGHT or '0.25', onEndEdit='ui_editshields', characterValidation='Decimal'}},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=0, preferredWidth=100, childForceExpandWidth = false, childForceExpandHeight = false, childAlignment='MiddleCenter'}, children={
                        {tag='Text', attributes={text='Auto-on'}},
                        {tag='Button', attributes={minWidth=30, preferredHeight=30, preferredWidth=30, image=((currentconfig.SHIELDS.AUTOMODE or false) and 'ui_checkon' or 'ui_checkoff'), onClick='ui_editshields', id='inp_shield_automode'}},
                    }},
                    {tag='VerticalLayout', attributes={ flexibleWidth=1, preferredWidth = 400, childForceExpandHeight=false}, children={
                        {tag='Text', attributes={text='Limit Behaviour'}},
                        {tag='HorizontalLayout', attributes={spacing='5'}, children={
                            {tag='ToggleButton', attributes={onClick='ui_shieldslimitmode('..const.SHIELD_CAPATLIMIT..')', text='Cap at Limit', isOn=(currentconfig.SHIELDS.LIMITMODE == const.SHIELD_CAPATLIMIT)}},
                            {tag='ToggleButton', attributes={onClick='ui_shieldslimitmode('..const.SHIELD_WRAPAROUND..')', text='Wrap Around', isOn=(currentconfig.SHIELDS.LIMITMODE == const.SHIELD_WRAPAROUND)}},
                            {tag='ToggleButton', attributes={onClick='ui_shieldslimitmode('..const.SHIELD_IGNORELIMIT..')', text='Ignore Limit', isOn=(currentconfig.SHIELDS.LIMITMODE == const.SHIELD_IGNORELIMIT)}},
                        }},
                    }},
                }},
                {tag='VerticalLayout', attributes={ childForceExpandHeight=false }, children={
                    {tag='Text', attributes={text='Normal Color'}},
                    {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                        {tag='InputField', attributes={id='inp_shields_color', flexibleWidth=1, text=currentconfig.SHIELDS.COLOR, onEndEdit='ui_editshields'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_shields_setcolor(#ffffff)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_shields_setcolor(#713b17)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_shields_setcolor(#da1918)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_shields_setcolor(#f4641d)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_shields_setcolor(#e7e52c)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_shields_setcolor(#31b32b)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_shields_setcolor(#21b19b)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_shields_setcolor(#1f87ff)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_shields_setcolor(#a020f0)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_shields_setcolor(#f570ce)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_shields_setcolor(#191919)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_shields_setcolor(#aaaaaa)'}},
                    }}
                }},
                {tag='VerticalLayout', attributes={ childForceExpandHeight=false }, children={
                    {tag='Text', attributes={text='Critical Color'}},
                    {tag='HorizontalLayout', attributes={flexibleWidth=0, preferredHeight='40', spacing=5, childForceExpandWidth = false}, children={
                        {tag='InputField', attributes={id='inp_shields_critcolor', flexibleWidth=1, text=currentconfig.SHIELDS.CRITCOLOR, onEndEdit='ui_editshields'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='White|White|#808080|#40404040', onClick='ui_shields_setcritcolor(#ffffff)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Brown|Brown|#808080|#40404040', onClick='ui_shields_setcritcolor(#713b17)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Red|Red|#808080|#40404040', onClick='ui_shields_setcritcolor(#da1918)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Orange|Orange|#808080|#40404040', onClick='ui_shields_setcritcolor(#f4641d)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Yellow|Yellow|#808080|#40404040', onClick='ui_shields_setcritcolor(#e7e52c)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Green|Green|#808080|#40404040', onClick='ui_shields_setcritcolor(#31b32b)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Teal|Teal|#808080|#40404040', onClick='ui_shields_setcritcolor(#21b19b)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Blue|Blue|#808080|#40404040', onClick='ui_shields_setcritcolor(#1f87ff)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Purple|Purple|#808080|#40404040', onClick='ui_shields_setcritcolor(#a020f0)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Pink|Pink|#808080|#40404040', onClick='ui_shields_setcritcolor(#f570ce)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Black|Black|#808080|#40404040', onClick='ui_shields_setcritcolor(#191919)'}},
                        {tag='Button', attributes={image='ui_drop', preferredWidth='40', colors='Grey|Grey|#808080|#40404040', onClick='ui_shields_setcritcolor(#aaaaaa)'}},
                    }}
                }},
                {tag='Text', attributes={text='Shape'}},
                {tag='HorizontalLayout', attributes={spacing='5'}, children={
                    {tag='ToggleButton', attributes={onClick='ui_shieldsshape('..const.SHIELD_FRONTBACK..')', text='Fore/Aft', isOn=(currentconfig.SHIELDS.SHAPE == const.SHIELD_FRONTBACK)}},
                    {tag='ToggleButton', attributes={onClick='ui_shieldsshape('..const.SHIELD_LEFTRIGHT..')', text='Left/Right', isOn=(currentconfig.SHIELDS.SHAPE == const.SHIELD_LEFTRIGHT)}},
                    {tag='ToggleButton', attributes={onClick='ui_shieldsshape('..const.SHIELD_FOURWAY..')', text='4-Way', isOn=(currentconfig.SHIELDS.SHAPE == const.SHIELD_FOURWAY)}},
                    {tag='ToggleButton', attributes={onClick='ui_shieldsshape('..const.SHIELD_SIXWAY..')', text='6-Way', isOn=(currentconfig.SHIELDS.SHAPE == const.SHIELD_SIXWAY)}},
                }},
                shieldsOptions
            }}
        end

        local updateMe = {}
        if (needsUpdate == const.NEEDSUPDATE) then
            updateMe = {tag='Button', attributes={fontSize='22', text='Update Available', onClick='ui_setmode(SETTINGS)', flexibleWidth=1, colors = '#ffcc33|#ffffff|#808080|#606060'}}
        end

        table.insert(ui, {
            tag='Panel', attributes={position='0 -400 -60', height='10000', width='800', rectAlignment='UpperCenter'}, children={
                {tag='VerticalLayout', attributes={childForceExpandHeight=false, minHeight='0', spacing=5, rectAlignment='UpperCenter'}, children={
                    {tag='HorizontalLayout', attributes={preferredHeight=80, childForceExpandWidth=false, flexibleHeight=0, spacing=20, padding='10 10 10 10'}, children={
                        {tag='Button', attributes={fontSize='22', text='Load from Mini', onClick='ui_extract', flexibleWidth=1}},
                        {tag='Button', attributes={fontSize='22', text='Add', onClick='ui_inject', flexibleWidth=1}},
                        {tag='Button', attributes={fontSize='22', text='Remove', onClick='ui_clearmini', flexibleWidth=1}},
                        updateMe,
                        {tag='button', attributes={onClick='ui_setmode(SETTINGS)', image='ui_gear', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', preferredWidth='60', height='60', flexibleWidth=0}}
                    }},
                    basePanel,presetPanel,permViewPanel,permEditPanel,barsPanel,markersPanel,arcPanel,flagPanel,geometryPanel,movementPanel,shieldsPanel
                }}
            }
        })
    end
    if (ui_mode == 'SETTINGS') then

        local updateStatusDisplay = {tag='Text', attributes={id='meta_update_status', text='Update Autocheck is disabled', color='#ff3333'}}

        local changeDisplay = {}

        if (needsUpdate == const.UPTODATE) then
            updateStatusDisplay = {tag='Text', attributes={id='meta_update_status', text='MiniHUD Injector is up to date', color='#33ccff'}}
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
                            {tag='Button', attributes={colors='#ffcc33|#ffffff|#808080|#606060', onClick='ui_system_update', text='Update and Restart MiniHUD Injector', interactable = (needsUpdate == const.NEEDSUPDATE)}},
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
                        print('[ffcc33]There is a new version of The MiniHUD Injector available[-] ('..result.current..' -> '..result.new..')')
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


function onLoad(save)

    local data = JSON.decode(save) or {config = currentconfig, presets = {}}
    local cfg = data.config
    for i,v in pairs(currentconfig) do
        if (cfg[i] == nil) then
            cfg[i] = v
        end
    end
    currentconfig = cfg
    metaconfig = data.metaconfig or metaconfig
    currentpresets = data.presets or {}
    if (metaconfig.UPDATECHECK) then
        checkForUpdate()
    end

    local assetRoot = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/ui/';
    local assets = {
        {name='ui_plus', url=assetRoot..'plus.png'},
        {name='ui_gear', url=assetRoot..'gear.png'},
        {name='ui_power', url=assetRoot..'power.png'},
        {name='ui_close', url=assetRoot..'close.png'},
        {name='ui_minus', url=assetRoot..'minus.png'},
        {name='ui_save', url=assetRoot..'save.png'},
        {name='ui_load', url=assetRoot..'load.png'},
        {name='ui_help_outline', url=assetRoot..'help_outline.png'},
        {name='ui_drop', url=assetRoot..'drop.png'},
        {name='ui_share', url=assetRoot..'share.png'},
        {name='ui_locked', url=assetRoot..'locked.png'},
        {name='ui_unlocked', url=assetRoot..'unlocked.png'},
        {name='ui_checkon', url=assetRoot..'checkbox_on.png'},
        {name='ui_checkoff', url=assetRoot..'checkbox_off.png'},
    }
    for _,shape in pairs(arcshapes) do
        table.insert(assets, {
            name='arc_'..shape, url='https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/ui/arcs/'..shape..'.png'
        })
    end
    self.UI.setCustomAssets(assets)
    Wait.frames(rebuildUI, config.REFRESH)
end

function onSave()
    return JSON.encode({presets=currentpresets, config = currentconfig, metaconfig = metaconfig})
end";
        }
    }
}

?>
