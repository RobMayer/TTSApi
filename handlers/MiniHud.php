<?php

namespace handlers {

    class MiniHud {

        public function build($data) {
            $input = $data['inject'];
            $DEFAULT_REFRESH = "3";
            $res = "";
            $res .= "--ThatRobHuman MiniHUD\nTRH_Class = 'mini'\nTRH_Version = '5.0'\n";

            $res .= "TRH_Save = '".base64_encode(json_encode($data['config']))."'\n";
            $res .= "local state = {};";
            $res .= "local PERMEDIT = '".$input['PERMEDIT']."';";
            $res .= "local PERMVIEW = '".$input['PERMVIEW']."';";
            $res .= "local ui_mode = '0';";
            $res .= "local controller_obj;";
            $res .= "local assetBuffer = {};";

            if ($input['MODULE_ARC']) {
            	$res .= "local arc_len = 1;";
            	$res .= "local arc_obj;";
            }

            if ($input['MODULE_GEOMETRY']) {
            	$res .= "local geo_obj;";
            	$res .= "local geometry_reload = false;";
            }

            if ($input['MODULE_FLAG']) {
            	$res .= "local flag_active = false;";
            }

            if ($input['MODULE_MOVEMENT']) {

            	switch ($input['MOVEMENT']['MODE']) {
            		case 1: //simple gauge
            			$res .= "local move_speed = {0};";
            			break;
            		case 2: //restricted radius
            			$res .= "local move_speed = ".$input['MOVEMENT']['SPEEDMIN'].";";
            			break;
            		case 3: //complex gauge
            			$res .= "local move_speed = {0};";
            			$res .= "local move_segments = JSON.decode('".json_encode($input['MOVEMENT']['SEGMENTS'])."');";
            			break;
            		case 4: //predefined
            			$res .= "local move_speed = 0;";
            			$res .= "local move_definitions = JSON.decode('".json_encode($input['MOVEMENT']['DEFINITIONS'], JSON_UNESCAPED_SLASHES)."');";
            			break;
            	}
            	$res .= "local move_cache;";
            	$res .= "local move_store_pos;";
            	$res .= "local move_store_rot;";
            	$res .= "local move_active = false;";
            	$res .= "local move_obj;";
            	$res .= "\n";

            	if ($input['MOVEMENT']['MODE'] == 2) {
            		$res .= "function onUpdate()
            			if move_active and not self.resting then
            				local a=self.getPosition()
            				local b={a[1]-move_cache[1],move_cache[2],a[3]-move_cache[3]}
            				local c=math.pow(b[1],2)+math.pow(b[3],2)
            				if c>math.pow(move_speed,2)then
            					local d=math.sqrt(c)
            					self.setPosition({move_cache[1]+b[1]/d*move_speed,a[2],move_cache[3]+b[3]/d*move_speed})
            				end
            			end
            		end\n";
            	}
            }

            if ($input['MODULE_ARC']) {
            	if ($input['ARCS']['MODE'] == 3) { //brackets
            		$res .= "local arc_brackets = JSON.decode('".json_encode($input['ARCS']['BRACKETS'])."')\n";
            	}
            }

            $res .= "local rotateVector = function(a,b)
            	local c=math.rad(b)local d=math.cos(c)*a[1]+math.sin(c)*a[2]local e=math.sin(c)*a[1]*-1+math.cos(c)*a[2]return{d,e,a[3]}
            end\n";

            $res .= "local indexOf = function(e, t)
            	for k,v in pairs(t) do
            		if (e == v) then return k end
            	end
            	return nil
            end\n";

            // onDestroy
            if ($input['MODULE_ARC'] || $input['MODULE_GEOMETRY'] || ($input['MODULE_MOVEMENT'] && $input['MOVEMENT']['MODE'] == 2)) {
            	$res .= "function onDestroy()\n";
            		if ($input['MODULE_ARC']) { $res .= "if (arc_obj ~= nil) then arc_obj.destruct() end\n"; }
            		if ($input['MODULE_GEOMETRY']) { $res .= "if (geo_obj ~= nil) then geo_obj.destruct() end\n"; }
            		if ($input['MODULE_MOVEMENT']) { $res .= "if (move_obj ~= nil) then move_obj.destruct() end\n"; }
            		$res .= "if controller_obj ~= nil then initiateControllerUnlink() end\n";
            	$res .= "end\n";
            }

            // onSave
            $res .= "function onSave()";
            	$res .= "local data={}\n";
            	if ($input['MODULE_BARS']) { $res .= "data.bars=state.bars\n"; }
            	if ($input['MODULE_MARKERS']) { $res .= "data.markers=state.markers\n"; }
            	if ($input['MODULE_FLAG']) { $res .= "data.flag=state.flag\n"; }
            	if ($input['MODULE_GEOMETRY']) { $res .= "data.geometry=state.geometry\n"; }
            	$res .= "if controller_obj~=nil then a.controller=controller_obj.guid end\n";
            	$res .= "return JSON.encode(data)\n";
            $res .= "end\n";

            // onLoad
            $res .= "function onLoad(save)
            	save = JSON.decode(save) or {}
            	if (save.controller ~= nil) then
            		local theObj = getObjectFromGUID(save.controller)
            		if (theObj ~= nil) then
            			if (theObj.call('verifyLink', {guid=self.guid})) then
            				controller_obj = theObj
            			end
            		end
            	end
            ";
            if ($input['MODULE_BARS']) { $res .= "state.bars = save.bars or {}\n"; }
            if ($input['MODULE_MARKERS']) { $res .= "state.markers = save.markers or {}\n"; }
            if ($input['MODULE_FLAG']) { $res .= "state.flag = save.flag or {}\nflag_active = state.flag.automode or false\n"; }
            if ($input['MODULE_GEOMETRY']) { $res .= "state.geometry = save.geometry or {}\nstate.geometry.material = state.geometry.material or 0\nspawnGeometry()\n"; }

            $res .= "rebuildAssets()
            	Wait.frames(rebuildUI, ".($input['REFRESH'] ?? $DEFAULT_REFRESH).")
            end\n";

            $res .= "function ui_setmode(player,mode)
            	if mode==ui_mode then
            		mode='0'
            	end
            	ui_mode=mode
            	if mode=='0' then
            		rebuildAssets()
            		Wait.frames(rebuildUI,".($input['REFRESH'] ?? $DEFAULT_REFRESH).")\n";
            		if ($input['MODULE_GEOMETRY']) { $res .= "if (geometry_reload) then reloadGeometry() end\n"; }
            	$res .= " else
            		rebuildUI()
            	end
            end\n";

            $res .= "function initiateLink(data)
            	if (setController(data)) then
            		return controller_obj.call('setMini', {guid=self.guid})
            	end
            	return false
            end\n";

            $res .= "function initiateUnlink()
            	local theObj = unsetController()
            	if (theObj ~= nil) then
            		theObj.call('unsetMini', {guid = self.guid})
            	end
            end\n";

            $res .= "function verifyLink(data)
            	local obj = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
            	return (obj == controller_obj)
            end\n";

            $res .= "function setController(data)
            	local obj = data.object or getObjectFromGUID(data.guid or error('object or guid is required', 2)) or error('invalid object',2)
            	if ((obj.getVar('TRH_Class') or '') ~= 'mini.controller') then
            		error('object is not a mini controller',2)
            	else
            		controller_obj = obj
            		return true
            	end
            	return false
            end\n";

            $res .= "function unsetController()
            	if (controller_obj ~= nil) then
            		local theObj = controller_obj
            		controller_obj = nil
            		return theObj
            	end
            	return nil
            end\n";

            if ($input['MODULE_MOVEMENT']) {


            	if ($input['MOVEMENT']['MODE'] == 1) { //simple gauge
            		$res .= "function moveCommit()
            			local a=self.getPosition()
            			local b=self.getRotation()
            			local c=rotateVector(move_store_pos,b[2])
            			self.setPositionSmooth({a[1]+c[1]/100,a[2],a[3]+c[2]/100},false)
            			self.setRotationSmooth({b[1],b[2]+move_store_rot,b[3]},false)
            			move_store_pos={0,0,0}
            			move_store_rot=0
            			move_cache={0,0,0}
            			move_active=false
            			rebuildUI()
            		end\n";
                    $res .= "function moveCancel()
            			move_store_pos={0,0,0}
            			move_store_rot=0
            			move_cache={0,0,0}
            			move_active=false
            			rebuildUI()
            		end\n";
                    $res .= "function moveStart()
            			move_active=true
            			rebuildUI()
            		end\n";

            		$res .= "function ui_move_faster(player)
            			table.insert(move_speed,0)
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_slower(player)
            			tmp={}
            			for i,k in pairs(move_speed) do
            				if i < #move_speed then
            					table.insert(tmp,k)
            				end
            			end
            			move_speed=tmp
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_dec(player,index)
            			index=tonumber(index)
            			move_speed[index]=move_speed[index]-1
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_inc(player,index)
            			index=tonumber(index)
            			move_speed[index]=move_speed[index]+1
            			rebuildUI()
            		end\n";
            	}

            	if ($input['MOVEMENT']['MODE'] == 2) { //forced radius
            		$res .= "function moveCommit()
            			if move_obj~=nil then
            				move_obj.destruct()
            			end
            			move_cache={0,0,0}
            			move_active=false
            			rebuildUI()
            		end\n";
                    $res .= "function moveCancel()
            			self.setPositionSmooth(move_cache,false)
            			if move_obj~=nil then
            				move_obj.destruct()
            			end
            			move_cache={0,0,0}
            			move_active=false
            			rebuildUI()
            		end\n";
            		$res .= "function moveStart()
            			local a=move_speed+(".(strtoupper($input['MOVEMENT']['ORIGIN']) == "EDGE" ? (($input['BASE_LENGTH']+$input['BASE_WIDTH'])/4.0) : "0").")
            			move_cache=self.getPosition()
            			move_obj=spawnObject({
            				type='custom_model',
            				position=self.getPosition(),
            				rotation=self.getRotation(),
            				scale={a,1,a},
            				mass=0,
            				sound=false,
            				snap_to_grid=false,
            				callback_function=function(b)
            					b.getComponent('MeshRenderer').set('receiveShadows',false)
            					b.mass=0
            					b.bounciness=0
            					b.drag=0
            					b.use_snap_points=false
            					b.use_grid=false
            					b.use_gravity=false
            					b.auto_raise=false
            					b.auto_raise=false
            					b.sticky=false
            					b.interactable=false
            					b.setLock(true)
            				end
            			})
            			move_obj.setCustomObject({
            				mesh='https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/components/arcs/round0.obj',
            				collider='https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/utility/null_COL.obj',
            				material=3,
            				specularIntensity=0,
            				cast_shadows=false
            			})
            			move_active=true
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_faster(a)
            			move_speed=move_speed+1
            			if move_obj~=nil then
            				local theScale=move_speed+(".(strtoupper($input['MOVEMENT']['ORIGIN']) == "EDGE" ? (($input['BASE_LENGTH']+$input['BASE_WIDTH'])/4.0) : "0").")
            				move_obj.setScale({theScale,1,theScale})
            			end
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_slower(a)
            			move_speed=move_speed-1
            			if move_obj~=nil then
            				local theScale=move_speed+(".(strtoupper($input['MOVEMENT']['ORIGIN']) == "EDGE" ? (($input['BASE_LENGTH']+$input['BASE_WIDTH'])/4.0) : "0").")
            				move_obj.setScale({theScale,1,theScale})
            			end
            			rebuildUI()
            		end\n";
            	}

            	if ($input['MOVEMENT']['MODE'] == 3) { //complex gauge
            		$res .= "function moveCommit()
            			local a=self.getPosition()
            			local b=self.getRotation()
            			local c=rotateVector(move_store_pos,b[2])
            			self.setPositionSmooth({a[1]+c[1]/100,a[2],a[3]+c[2]/100},false)
            			self.setRotationSmooth({b[1],b[2]+move_store_rot,b[3]},false)
            			move_store_pos={0,0,0}
            			move_store_rot=0
            			move_cache={0,0,0}
            			move_active=false
            			rebuildUI()
            		end\n";
            		$res .= "function moveStart()
            			move_active=true
            			rebuildUI()
            		end\n";
            		$res .= "function moveCancel()
            			move_store_pos={0,0,0}
            			move_store_rot=0
            			move_cache={0,0,0}
            			move_active=false
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_faster(player)
            			table.insert(move_speed,0)
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_slower(player)
            			tmp={}
            			for i,k in pairs(move_speed) do
            				if i<#move_speed then
            					table.insert(tmp,k)
            				end
            			end
            			move_speed=tmp
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_dec(player,index)
            			index=tonumber(index) or 0
            			move_speed[index]=move_speed[index]-1;
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_inc(player,index)
            			index=tonumber(index) or 0
            			move_speed[index]=move_speed[index]+1;
            			rebuildUI()
            		end\n";
            	}

            	if ($input['MOVEMENT']['MODE'] == 4) { //defined
            		$res .= "function moveCommit()
            			local a=self.getPosition()
            			local b=self.getRotation()
            			if move_speed>0 then
            				local c=move_definitions[move_speed]
            				local d=rotateVector({c[5],c[6],0},b[2])
            				self.setPositionSmooth({a[1]+d[1],a[2],a[3]+d[2]},false)
            				self.setRotationSmooth({b[1],b[2]+c[7],b[3]},false)
            				move_speed=0
            			end
            			move_active=false
            			rebuildUI()
            		end\n";
            		$res .= "function moveStart()
            			move_active=true
            			rebuildUI()
            		end\n";
            		$res .= "function moveCancel()
            			move_active=false
            			rebuildUI()
            		end\n";
            		$res .= "function ui_move_select(player,index)
            			move_speed=tonumber(index) or 0
            			rebuildUI()
            		end\n";
            	}
            	$res .= "function ui_move_commit(player) moveCommit() end\n";
            	$res .= "function ui_move_cancel(player) moveCancel() end\n";
            	$res .= "function ui_move(player) if (move_active) then moveCancel() else moveStart() end end\n";
            } else {
            	$res .= "function moveCommit() end;\n";
            	$res .= "function moveCancel() end;\n";
            	$res .= "function moveStart() end;\n";
            }

            if ($input['MODULE_GEOMETRY']) {

            	$res .= "function spawnGeometry()
            	    if (geo_obj ~= nil) then
            	        geo_obj.destruct()
            	    end
            	    if (state.geometry.mesh ~= nil and state.geometry.mesh ~= '') then
            	        geo_obj = spawnObject({
            	            type = 'custom_model',
            	            position = self.getPosition(),
            	            rotation = self.getRotation(),
            	            scale = self.getScale(),
            	            mass = 0,
            	            sound = false,
            	            snap_to_grid = false,
            	            callback_function = function(obj)
            	                if (string.lower(state.geometry.color or 'INHERIT') == 'inherit') then
            	                    obj.setColorTint(self.getColorTint())
            	                else
            	                    local clr = string.sub(state.geometry.color, 2, 7) or 'ffffff'
            	                    if (string.len(clr) ~= 6) then clr = 'ffffff' end
            	                    obj.setColorTint({
            	                        (tonumber(string.sub(clr, 1, 2),16) or 255) / 255,
            	                        (tonumber(string.sub(clr, 3, 4),16) or 255) / 255,
            	                        (tonumber(string.sub(clr, 5, 6),16) or 255) / 255,
            	                    })
            	                end
                                (obj.getComponent('MeshCollider') or obj.getComponent('BoxCollider')).set('enabled', false)
            	                obj.setVar('parent', self)
            	                obj.setLuaScript('function onUpdate() if (parent ~= nil) then if (not parent.resting) then self.setPosition(parent.getPosition()) self.setRotation(parent.getRotation()) self.setScale(parent.getScale()) end else self.destruct() end end')
            	                obj.mass = 0
            	                obj.bounciness = 0
            	                obj.drag = 0
            	                obj.use_snap_points = false
            	                obj.use_grid = false
            	                obj.use_gravity = false
            	                obj.auto_raise = false
            	                obj.auto_raise = false
            	                obj.sticky = false
            	                obj.interactable = false
            	            end,
            	        })
            	        geo_obj.setCustomObject({
            	            mesh = state.geometry.mesh or '',
            	            diffuse = state.geometry.texture or '',
            	            normal = state.geometry.normal or '',
            	            collider = '',
            	            type = 0,
            	            material = state.geometry.material or 0,
            	        })
            	        geometry_reload=false;
            	    end
            	end\n";

            } else {
            	$res .= "function spawnGeometry() end;\n";
            }

            if ($input['MODULE_GEOMETRY'] && !$input['LOCK_GEOMETRY']) {

            	$res .= "function editGeometry(data)
            		local mesh = data.mesh or state.geometry.mesh
            		local texture = data.texture or state.geometry.texture
            		local normal = data.normal or state.geometry.normal
            		local material = tonumber(data.material) or state.geometry.material
            		local color = data.color or state.geometry.color

            		state.geometry.mesh = mesh
            		state.geometry.texture = texture
            		state.geometry.normal = normal
            		state.geometry.material = material
            		state.geometry.color = color

            		self.UI.setAttribute('inp_geometry_mesh', 'text', mesh)
            		self.UI.setAttribute('inp_geometry_texture', 'text', texture)
            		self.UI.setAttribute('inp_geometry_normal', 'text', normal)
            		self.UI.setAttribute('inp_geometry_material_0', 'isOn', material == 0)
            		self.UI.setAttribute('inp_geometry_material_1', 'isOn', material == 1)
            		self.UI.setAttribute('inp_geometry_material_2', 'isOn', material == 2)
            		self.UI.setAttribute('inp_geometry_material_3', 'isOn', material == 3)
            		self.UI.setAttribute('inp_geometry_color', 'text', color)
            		geometry_reload = true
            	end\n";
            	$res .= "function clearGeometry()
            	    state.geometry.mesh='';
            	    state.geometry.texture='';
            	    state.geometry.normal='';
            	    state.geometry.material=0;
            	    state.geometry.color='inherit';
            	    if (geo_obj ~= nil) then geo_obj.destruct(); end;
            	end\n";
                $res .= "function spawnGeometry()
                	if (geo_obj ~= nil) then
                		geo_obj.destruct()
                	end
                	if (state.geometry.mesh ~= nil and state.geometry.mesh ~= '') then
                		geo_obj = spawnObject({
                			type = 'custom_model',
                			position = self.getPosition(),
                			rotation = self.getRotation(),
                			scale = self.getScale(),
                			mass = 0,
                			sound = false,
                			snap_to_grid = false,
                			callback_function = function(obj)
                				if (string.lower(state.geometry.color or 'INHERIT') == 'inherit') then
                					obj.setColorTint(self.getColorTint())
                				else
                					local clr = string.sub(state.geometry.color, 2, 7) or 'ffffff'
                					if (string.len(clr) ~= 6) then clr = 'ffffff' end
                					obj.setColorTint({
                						(tonumber(string.sub(clr, 1, 2),16) or 255) / 255,
                						(tonumber(string.sub(clr, 3, 4),16) or 255) / 255,
                						(tonumber(string.sub(clr, 5, 6),16) or 255) / 255,
                					})
                				end
                				obj.setVar('parent', self)
                				obj.setLuaScript('function onUpdate() if (parent ~= nil) then if (not parent.resting) then self.setPosition(parent.getPosition()) self.setRotation(parent.getRotation()) self.setScale(parent.getScale()) end else self.destruct() end end')
                				obj.mass = 0
                				obj.bounciness = 0
                				obj.drag = 0
                				obj.use_snap_points = false
                				obj.use_grid = false
                				obj.use_gravity = false
                				obj.auto_raise = false
                				obj.auto_raise = false
                				obj.sticky = false
                				obj.interactable = false
                			end,
                		})
                		geo_obj.setCustomObject({
                			mesh = state.geometry.mesh or '',
                			diffuse = state.geometry.texture or '',
                			normal = state.geometry.normal or '',
                			collider = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/utility/null_COL.obj',
                			type = 0,
                			material = state.geometry.material or 0,
                		})
                        geometry_reload=false;
                	end
                end;\n";
            	$res .= "function reloadGeometry()
                    if (geo_obj ~= nil) then
                        if (state.geometry.mesh ~= nil and state.geometry.mesh ~= '') then
                            if (string.lower(state.geometry.color or 'INHERIT') == 'inherit') then
                                geo_obj.setColorTint(self.getColorTint())
                            else
                                local clr = string.sub(state.geometry.color, 2, 7) or 'ffffff'
                                if (string.len(clr) ~= 6) then clr = 'ffffff' end
                                geo_obj.setColorTint({
                                    (tonumber(string.sub(clr, 1, 2),16) or 255) / 255,
                                    (tonumber(string.sub(clr, 3, 4),16) or 255) / 255,
                                    (tonumber(string.sub(clr, 5, 6),16) or 255) / 255,
                                })
                            end
                            geo_obj.setCustomObject({
                                mesh = state.geometry.mesh or '',
                    			diffuse = state.geometry.texture or '',
                    			normal = state.geometry.normal or '',
                    			collider = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/utility/null_COL.obj',
                    			type = 0,
                    			material = state.geometry.material or 0,
                            })
                            geo_obj.reload()
                        else
                            geo_obj.destruct();
                        end
                        geometry_reload=false;
                    else
                        spawnGeometry()
                        geometry_reload=false;
                    end
                end;\n";

            	$res .= "function ui_editgeometry(player, val, id)
            		local args = {}
            	    for a in string.gmatch(id, '([^%_]+)') do
            	        table.insert(args,a)
            	    end
            	    local key = args[3]
            	    if (key == 'mesh') then
            	        editGeometry({mesh=val})
            	    elseif (key == 'texture') then
            	        editGeometry({texture=val})
            	    elseif (key == 'normal') then
            	        editGeometry({normal=val})
            	    elseif (key == 'material') then
            			editGeometry({material=val})
            	    elseif (key == 'color') then
            	        editGeometry({color=val})
            	    end
            		geometry_reload = true
            	end\n";
            	$res .= "function ui_cleargeometry(player, value, id) clearGeometry(); geometry_reload = true; end;\n";
            	$res .= "function ui_geometry_setcolor(player, value, id) ui_editgeometry(player, value, 'inp_geometry_color'); end;\n";

            } else {
            	$res .= "function editGeometry(a) end;\n";
            	$res .= "function clearGeometry() end;\n";
            	$res .= "function reloadGeometry() end;\n";
            }

            if ($input['MODULE_ARC']) {

            	$scaleString = "";
            	if ($input['ARCS']['MODE'] == 1) { //incremental
            		$scaleString = $input['ARCS']['SCALE']."*(arc_len+(".$input['ARCS']['ZERO']."))";
            	}
            	if ($input['ARCS']['MODE'] == 2) { //static
            		$scaleString = $input['ARCS']['SCALE'];
            	}
            	if ($input['ARCS']['MODE'] == 3) { //brackets
            		$scaleString = $input['ARCS']['SCALE']."*(arc_brackets[arc_len]+(".$input['ARCS']['ZERO']."))";
            	}

            	$res .= "function showArc()
            		self.UI.hide('btn_show_arc')
            		self.UI.show('btn_hide_arc')
            		self.UI.show('disp_arc_len')
            		self.UI.show('btn_arc_sub')
            		self.UI.show('btn_arc_add')
            		local a=".$scaleString."\n";
            	if (strtolower($input['ARCS']['COLOR']) == "inherit") {
            		$res .= "local clr = self.getColorTint()\n";
            	} else {
            		$res .= "local tmp = string.sub('".$input['ARCS']['COLOR']."', 2, 7) or 'ffffff'
            		if (string.len(tmp) ~= 6) then tmp = 'ffffff' end
            		local clr = {
            			(tonumber(string.sub(tmp, 1, 2),16) or 255) / 255,
            			(tonumber(string.sub(tmp, 3, 4),16) or 255) / 255,
            			(tonumber(string.sub(tmp, 5, 6),16) or 255) / 255,
            		}\n";
            	}
            	$res .= " arc_obj=spawnObject({
            			type='custom_model',
            			position=self.getPosition(),
            			rotation=self.getRotation(),
            			scale={a,1,a},
            			mass=0,
            			sound=false,
            			snap_to_grid=false,
            			callback_function=function(b)
            				b.setColorTint(clr)
            				b.setVar('parent',self)
            				b.setLuaScript('function onUpdate() if (parent ~= nil) then if (not parent.resting) then self.setPosition(parent.getPosition()) self.setRotation(parent.getRotation()) end else self.destruct() end end')
            				b.getComponent('MeshRenderer').set('receiveShadows',false)
            				b.mass=0
            				b.bounciness=0
            				b.drag=0
            				b.use_snap_points=false
            				b.use_grid=false
            				b.use_gravity=false
            				b.auto_raise=false
            				b.auto_raise=false
            				b.sticky=false
            				b.interactable=false
            			end
            		})
            		arc_obj.setCustomObject({
            			mesh='".$input['ARCS']['MESH']."',
            			collider='https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/utility/null_COL.obj',
            			material=3,
            			specularIntensity=0,
            			cast_shadows=false
            		})
            	end\n";

            	if ($input['ARCS']['MODE'] == 1) {
            		$res .= "function setArcValue(a)
            			if arc_obj~=nil then
            				arc_len=tonumber(a.value) or arc_len;
            				arc_obj.setScale({".$scaleString.",1,".$scaleString."})
            				self.UI.setAttribute('disp_arc_len','text',arc_len)
            			end
            		end\n";
            		$res .= "function arcSub()
            			if arc_obj~=nil then
            				arc_len=math.max(1,arc_len-1)
            				arc_obj.setScale({".$scaleString.",1,".$scaleString."})
            				self.UI.setAttribute('disp_arc_len','text',arc_len)
            			end
            		end\n";
            		$res .= "function arcAdd()
            			if arc_obj~=nil then
            				arc_len=math.min(".$input['ARCS']['MAX'].",arc_len+1)
            				arc_obj.setScale({".$scaleString.",1,".$scaleString."})
            				self.UI.setAttribute('disp_arc_len','text',arc_len)
            			end
            		end\n";
            		$res .= "function ui_arcadd(player) arcAdd() end;\n";
            		$res .= "function ui_arcsub(player) arcSub() end;\n";
            	}

            	if ($input['ARCS']['MODE'] == 2) { // static
            		$res .= "function setArcValue() end;\n";
            		$res .= "function arcSub() end;\n";
            		$res .= "function arcAdd() end;\n";
            	}
            	if ($input['ARCS']['MODE'] == 3) { //Brackets
            		$res .= "function setArcValue(a)
            			if arc_obj~=nil then
            				local n = tonumber(a.value) or arc_brackets[arc_len]
            				if (indexOf(n, arc_brackets) ~= nil) then
            					arc_len = n
            					arc_obj.setScale({".$scaleString.",1,".$scaleString."})
            					self.UI.setAttribute('disp_arc_len','text',arc_brackets[arc_len])
            				end
            			end
            		end\n";
            		$res .= "function arcSub()
            			if arc_obj~=nil then
            				arc_len=math.max(1,arc_len-1)
            				arc_obj.setScale({".$scaleString.",1,".$scaleString."})
            				self.UI.setAttribute('disp_arc_len','text',arc_brackets[arc_len])
            			end
            		end\n";
            		$res .= "function arcAdd()
            			if arc_obj~=nil then
            				arc_len=math.min(#arc_brackets,arc_len+1)
            				arc_obj.setScale({".$scaleString.",1,".$scaleString."})
            				self.UI.setAttribute('disp_arc_len','text',arc_brackets[arc_len])
            			end
            		end\n";
            		$res .= "function ui_arcadd(player) arcAdd() end;\n";
            		$res .= "function ui_arcsub(player) arcSub() end;\n";
            	}
            	$res .= "function hideArc()
            		if arc_obj ~=nil then
            			arc_obj.destruct()
            		end
            		self.UI.show('btn_show_arc')
            		self.UI.hide('btn_hide_arc')
            		self.UI.hide('disp_arc_len')
            		self.UI.hide('btn_arc_sub')
            		self.UI.hide('btn_arc_add')
            	end\n";
            	$res .= "function ui_showarc(player) showArc() end;\n";
            	$res .= "function ui_hidearc(player) hideArc() end;\n";
            } else {
            	$res .= "function showArc() end;\n";
            	$res .= "function hideArc() end;\n";
            	$res .= "function setArcValue() end;\n";
            	$res .= "function arcSub() end;\n";
            	$res .= "function arcAdd() end;\n";
            }

            if ($input['MODULE_FLAG']) {
            	$res .= "function toggleFlag()
            		flag_active=not flag_active
            		if flag_active then
            			self.UI.show('flag_container')
            		else
            			self.UI.hide('flag_container')
            		end
            	end\n";
            	$res .= "function ui_flag(a) toggleFlag() end;\n";
            }

            if ($input['MODULE_FLAG'] && !$input['LOCK_FLAG']) {

            	$res .= "function editFlag(data)
            	    if (data.image ~= nil) then
            			state.flag.image = data.image
            	        self.UI.setAttribute('inp_flag_image', 'value', data.image)
            	    end
            	    if (data.width ~= nil) then
            	        local n = tonumber(data.width)
            	        if (n ~= nil) then
            	            state.flag.width = n
            	        end
            	        self.UI.setAttribute('inp_flag_width', 'value', data.width)
            	    end
            	    if (data.height ~= nil) then
            	        local n = tonumber(data.height)
            	        if (n ~= nil) then
            	            state.flag.height = n
            	        end
            	        self.UI.setAttribute('inp_flag_height', 'value', data.height)
            	    end
            	    if (data.color ~= nil) then
            	        if (string.len(data.color) == 7 and string.sub(data.color, 1, 1) == '#') then
            	            state.flag.color = data.color
            	        end
            	        self.UI.setAttribute('inp_flag_color', 'value', data.color)
            	    end
            	    if (data.automode ~= nil) then
            	        state.flag.automode = data.automode
            	        self.UI.setAttribute('inp_flag_automode', 'isOn', data.automode)
            	        flag_active = data.automode
            	    end
            	end\n";
            	$res .= "function clearFlag()
            		state.flag.image = nil
            		self.UI.setAttribute('inp_flag_image', 'value', '')
            		state.flag.width = 0
            		self.UI.setAttribute('inp_flag_width', 'value', 0)
            		state.flag.height = 0
            		self.UI.setAttribute('inp_flag_height', 'value', 0)
            		state.flag.color = '#ffffff'
            		self.UI.setAttribute('inp_flag_color', 'value', '#ffffff')
            		state.flag.automode = false
            		self.UI.setAttribute('inp_flag_automode', 'isOn', false)
            	end\n";
            	$res .= "function ui_editflag(player, val, id)
            	    local args = {}
            	    for a in string.gmatch(id, '([^%_]+)') do
            	        table.insert(args,a)
            	    end
            	    local key = args[3]
            	    if (key == 'image') then
            	        editFlag({image=val})
            	    elseif (key == 'color') then
            	        editFlag({color=val})
            	    elseif (key == 'width') then
            	        editFlag({width=val})
            	    elseif (key == 'height') then
            	        editFlag({height=val})
            	    elseif (key == 'automode') then
            	        editFlag({automode=(val == 'True')})
            	    end
            	end\n";
            	$res .= "function ui_clearflag(a) clearFlag() end;\n";
            } else {
            	$res .= "function toggleFlag() end;\n";
            	$res .= "function editFlag() end;\n";
            	$res .= "function clearFlag() end;\n";
            }


            if ($input['MODULE_MARKERS']) {
            	$res .= "function addMarker(data)
            	    local added = false
            	    local found = false
            	    local count = data.count or 1
            	    for i,each in pairs(state.markers) do
            	        if (each[1] == data.name) then
            	            found=true
            	            if (data.stacks or false) then
            	                cur = (state.markers[i][4] or 1) + count
            	                state.markers[i][4] = cur
            	                self.UI.setAttribute('counter_mk_'..i, 'text', cur)
            	                self.UI.setAttribute('disp_mk_'..i, 'text', cur > 1 and cur or '')
            	                --if (controller_obj ~= nil) then controller_obj.call('alterMiniMarker', { guid = self.guid, index=i, count=cur }) end
            	                added = true
            	            end
            	            break
            	        end
            	    end
            	    if (found == false) then
            	        table.insert(state.markers, {data.name, data.url, data.color or '#ffffff', (data.stacks or false) and count or 1, data.stacks or false})
            	        rebuildAssets()
            	        Wait.frames(rebuildUI, ".($input['REFRESH'] ?? $DEFAULT_REFRESH).")
            	        added = true
            	    end
            	    return added
            	end
            	\n";
            	$res .= "function getMarkers()
            	    res = {}
            	    for i,v in pairs(state.markers) do
            	        res[i] = {
            	            name = v[1],
            	            url = v[2],
            	            color = v[3],
            	            count = v[4] or 1,
            	            stacks = v[5] or false,
            	        }
            	    end
            	    return res
            	end\n";
            	$res .= "function popMarker(data)
            	    local i = tonumber(data.index)
            	    local cur = state.markers[i][4] or 1
            	    if (cur > 1) then
            	        cur = cur - (data.amount or 1)
            	        state.markers[i][4] = cur
            	        local display = ((cur > 1) and cur or '')
            	        self.UI.setAttribute('counter_mk_'..i, 'text', display)
            	        self.UI.setAttribute('disp_mk_'..i, 'text', display)
            	        --if (controller_obj ~= nil) then controller_obj.call('alterMiniMarker', { guid = self.guid, index=i, count=cur }) end
            	    else
            	        table.remove(state.markers, i)
            	        --if (controller_obj ~= nil) then controller_obj.call('updateMiniMarkers', {}) end
            	        rebuildUI()
            	    end
            	end\n";
            	$res .= "function removeMarker(data)
            		local index = tonumber(data.index) or error('index must be numeric');
            	    local tmp = {}
            	    for i,marker in pairs(state.markers) do
            	        if (i ~= data.index) then
            	            table.insert(tmp, marker)
            	        end
            	    end
            	    state.markers = tmp
            	    rebuildUI()
            	end\n";
            	$res .= "function clearMarkers()
            	    state.markers={}
            	    rebuildUI()
            	end\n";

            	$res .= "function ui_popmarker(player,value) popMarker({index=value}) end\n";

            } else {
            	$res .= "function addMarker(a) end;\n";
            	$res .= "function getMarkers()return {} end;\n";
            	$res .= "function popMarker(a)end;\n";
            	$res .= "function removeMarker(a)end;\n";
            	$res .= "function clearMarkers(a)end;\n";
            }

            //Bars
            if ($input['MODULE_BARS']) {
            	$res .= "function addBar(data)
            		local def = tonumber(data.current or data.maximum or 10)
            		local cur = data.current or def
            		local max = data.maximum or def
            		if (cur < 0) then cur = 0 end
            		if (max < 1) then max = 10 end
            		if (cur > max) then cur = max end
            		table.insert(state.bars, {
            			data.name or 'Name',
            			data.color or '#ffffff',
            			cur,
            			max,
            			data.text or false,
                        data.big or false,
            		})
            	end\n";
            	$res .= "function getBars()
            	    res = {}
            	    for i,v in pairs(state.bars) do
            	        local isBig = false
            	        local hasText = false
            	        if (v[5] ~= nil) then
            	            hasText = v[5]
            	        end
            	        if (v[6] ~= nil) then
            	            isBig = v[6]
            	        end
            	        res[i] = {
            	            name = v[1],
            	            color = v[2],
            	            current = v[3],
            	            maximum = v[4],
            	            text = hasText,
                            big = isBig,
            	        }
            	    end
            	    return res
            	end\n";
            	$res .= "function editBar(data)
            	    local index = tonumber(data.index) or error('index must be numeric', 2)
            	    local bar = state.bars[index]
            	    local max = tonumber(data.maximum) or bar[4]
            	    local cur = math.min(max, tonumber(data.current) or bar[3])
            	    local name = data.name or bar[1]
            	    local color = data.color or bar[2]
            	    local isBig = false
            	    local hasText = false
                    if (bar[5] ~= nil) then
            	        hasText = bar[5]
            	    end
            	    if (data.text ~= nil) then
            	        hasText = data.text
            	    end
            	    if (bar[6] ~= nil) then
            	        isBig = bar[6]
            	    end
            	    if (data.big ~= nil) then
            	        isBig = data.big
            	    end

            	    local per = (max == 0) and 0 or cur / max * 100

            	    self.UI.setAttribute('inp_bar_'..index..'_name', 'value', name)
            	    self.UI.setAttribute('inp_bar_'..index..'_color', 'value', color)
            	    self.UI.setAttribute('inp_bar_'..index..'_current', 'value', cur)
            	    self.UI.setAttribute('inp_bar_'..index..'_max', 'value', max)
                    self.UI.setAttribute('inp_bar_'..index..'_text', 'isOn', hasText)
            	    self.UI.setAttribute('inp_bar_'..index..'_big', 'isOn', isBig)

            	    self.UI.setAttribute('bar_'..index, 'percentage', per)
            	    self.UI.setAttribute('bar_'..index, 'fillImageColor', color)
            	    self.UI.setAttribute('bar_container_'..index, 'minHeight', isBig and 30 or 15)
            	    self.UI.setAttribute('bar_text_'..index, 'active', hasText)
            	    self.UI.setAttribute('bar_text_'..index, 'text', cur..' / '..max)

            	    state.bars[index][1] = name
            	    state.bars[index][2] = color
            	    state.bars[index][3] = cur
            	    state.bars[index][4] = max
                    state.bars[index][5] = hasText
            	    state.bars[index][6] = isBig
            	end\n";
            	$res .= "function adjustBar(data)
            	    local index = tonumber(data.index) or error('index must numeric')
            	    local val = tonumber(data.amount) or error('amount must be numeric')
            	    local bar = state.bars[index]
            	    local max = tonumber(bar[4]) or 0
            	    local cur = math.max(0, math.min(max, (tonumber(bar[3]) or 0) + val))
            	    local per = (max == 0) and 0 or cur / max * 100
            	    self.UI.setAttribute('bar_'..index, 'percentage', per)
            	    self.UI.setAttribute('bar_'..index..'_text', 'text', cur..' / '..max)
            	    self.UI.setAttribute('inp_bar_'..index..'_current', 'text', cur)
            	    state.bars[index][3] = cur
            	end\n";
            	$res .= "function removeBar(data)
            		local index = tonumber(data.index) or error('index must be numeric');
            	    local tmp = {}
            	    for i,bar in pairs(state.bars) do
            	        if (i ~= data.index) then
            	            table.insert(tmp, bar)
            	        end
            	    end
            	    state.bars = tmp
            	    rebuildUI()
            	end\n";
            	$res .= "function clearBars(data)
            		state.bars={}
            	    rebuildUI()
            	end\n";

            	//UI Hooks
            	$res .= "function ui_addbar(player)
            		addBar({name='Name', color='#ffcc33', current=10, maximum=10, big=false, text=false})
            	end\n";
            	$res .= "function ui_removebar(player, index)
            		removeBar({index=index})
            	end\n";
            	$res .= "function ui_editbar(player, val, id)
            		local args = {}
            		for a in string.gmatch(id, '([^%_]+)') do
            			table.insert(args,a)
            		end
            		local index = tonumber(args[3])
            		local key = args[4]
            		if (key == 'name') then
            			editBar({index=index, name=val})
            		elseif (key == 'color') then
            			editBar({index=index, color=val})
            		elseif (key == 'current') then
            			editBar({index=index, current=val})
            		elseif (key == 'max') then
            			editBar({index=index, maximum=val})
            		elseif (key == 'big') then
            			editBar({index=index, big=(val == 'True')})
            		elseif (key == 'text') then
            			editBar({index=index, text=(val == 'True')})
            		end
            	end\n";
            	$res .= "function ui_adjbar(player, id)
            	    local args = {}
            	    for a in string.gmatch(id, '([^%|]+)') do
            	        table.insert(args,a)
            	    end
            	    local index = tonumber(args[1]) or 1
            	    local amount = tonumber(args[2]) or 1
            	    adjustBar({index=index, amount=amount})
            	end\n";
            	$res .= "function ui_clearbars(player)
            		clearBars()
            	end;\n";
            } else {
            	$res .= "function addBar(data) end\n";
            	$res .= "function getBars(data) return {} end\n";
            	$res .= "function editBar(data) end\n";
            	$res .= "function adjustBar(data) end\n";
            	$res .= "function removeBar(data) end\n";
            	$res .= "function clearBars(data) end\n";
            }

            //REBUILD ASSETS
            $res .= "function rebuildAssets()
            	local root = 'https://raw.githubusercontent.com/RobMayer/TTSLibrary/master/ui/';
                local assets = {
                    {name='ui_gear', url=root..'gear.png'},
                    {name='ui_close', url=root..'close.png'},
                    {name='ui_plus', url=root..'plus.png'},
                    {name='ui_minus', url=root..'minus.png'},
                    {name='ui_hide', url=root..'hide.png'},
                    {name='ui_bars', url=root..'bars.png'},
                    {name='ui_stack', url=root..'stack.png'},
                    {name='ui_effects', url=root..'effects.png'},
                    {name='ui_reload', url=root..'reload.png'},
                    {name='ui_arcs', url=root..'arcs.png'},
                    {name='ui_flag', url=root..'flag.png'},
                    {name='ui_arrow_l', url=root..'arrow_l.png'},
                    {name='ui_arrow_r', url=root..'arrow_r.png'},
                    {name='ui_arrow_u', url=root..'arrow_u.png'},
                    {name='ui_arrow_d', url=root..'arrow_d.png'},
                    {name='ui_check', url=root..'check.png'},
                    {name='ui_block', url=root..'block.png'},
                    {name='ui_splitpath', url=root..'splitpath.png'},
                    {name='ui_cube', url=root..'cube.png'},
                    {name='movenode', url=root..'movenode.png'},
                    {name='moveland', url=root..'moveland.png'},
                }
            	assetBuffer = {}
            	local bufLen = 0
            	";
            	if ($input['MODULE_FLAG']) {
            		$res .= "if (state.flag.image ~= nil and state.flag.image ~= '' and state.flag.width ~= nil and state.flag.height ~= nil and state.flag.width > 0 and state.flag.height > 0) then
            			table.insert(assets, {name='fl_image', url=state.flag.image})
            		end
            		";
            	}
            	if ($input['MODULE_MARKERS']) {
            		$res .= "for i,marker in pairs(state.markers) do
            	        if (assetBuffer[marker[2]] == nil) then
            	            bufLen = bufLen + 1
            	            assetBuffer[marker[2]] = self.guid..'_asset_'..bufLen
            	            table.insert(assets, {name=self.guid..'_asset_'..bufLen, url=marker[2]})
            	        end
            	    end
            		"; }
            	if ($input['MODULE_MOVEMENT'] && $input['MOVEMENT']['MODE'] == 4) {
            		$res .= "for i,def in pairs(move_definitions) do
            			if (assetBuffer[def[2]] == nil) then
            				bufLen = bufLen + 1
            				assetBuffer[def[2]] = self.guid..'_asset_'..bufLen
            				table.insert(assets, {name=self.guid..'_asset_'..bufLen, url=def[2]})
            			end
            		end
            		";
            	}
            	$res .= "self.UI.setCustomAssets(assets)
            end\n";


            //REBUILD UI
            $res .= "function rebuildUI()\n";

        	$res .= "local w = ".max(100, $input['OVERHEAD_WIDTH'] / ($input['UI_SCALE']) * 100)."; local orient = '".$input['OVERHEAD_ORIENT']."';\n";

        	//Main Buttons
        	$res .= "local mainButtons = {};\n";
        	$res .= "local mainButtonX = 20;\n";
        	if ($input['MODULE_ARC']) {
        		$res .= "local arcActive = arc_obj ~= nil;\n";
        		$res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_show_arc', active=(not arcActive), height='30', width='30', rectAlignment='MiddleLeft', image='ui_arcs', offsetXY=mainButtonX..' 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_showarc', visibility=PERMEDIT}});\n";
                $res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_hide_arc', active=(arcActive), height='30', width='30', rectAlignment='LowerLeft', image='ui_arcs', offsetXY=mainButtonX..' 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_hidearc', visibility=PERMEDIT}});\n";
        		if ($input['ARCS']['MODE'] == 1) {
        			$res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_arc_sub', active=(arcActive and arc_len > 0), height='30', width='30', rectAlignment='LowerLeft', image='ui_minus', offsetXY='-70 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_arcsub', visibility=PERMEDIT}});\n";
        			$res .= "table.insert(mainButtons, {tag='text', attributes={id='disp_arc_len', active=(arcActive), height='30', width='30', rectAlignment='LowerLeft', text=arc_len, offsetXY='-40 0', color='#ffffff', fontSize='20', outline='#000000', visibility=PERMEDIT}});\n";
        			$res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_arc_add', active=(arcActive and arc_len < ".$input['ARCS']['MAX']."), height='30', width='30', rectAlignment='LowerLeft', image='ui_plus', offsetXY='-10 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_arcadd', visibility=PERMEDIT}});\n";
        		}
        		if ($input['ARCS']['MODE'] == 3) {
        			$res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_arc_sub', active=(arcActive and arc_len > 0), height='30', width='30', rectAlignment='LowerLeft', image='ui_minus', offsetXY='-70 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_arcsub', visibility=PERMEDIT}});\n";
        			$res .= "table.insert(mainButtons, {tag='text', attributes={id='disp_arc_len', active=(arcActive), height='30', width='30', rectAlignment='LowerLeft', text=arc_brackets[arc_len], offsetXY='-40 0', color='#ffffff', fontSize='20', outline='#000000', visibility=PERMEDIT}});\n";
        			$res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_arc_add', active=(arcActive and arc_len < #arc_brackets), height='30', width='30', rectAlignment='LowerLeft', image='ui_plus', offsetXY='-10 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_arcadd', visibility=PERMEDIT}});\n";
        		}
        		$res .= "mainButtonX = mainButtonX + 30;\n";
        	}
        	if ($input['MODULE_FLAG']) {
        		$res .= "local flagActive = (state.flag.image ~= nil and state.flag.image ~= '' and state.flag.height ~= nil and state.flag.width ~= nil and state.flag.height > 0 and state.flag.width > 0);\n";
        		$res .= "if (flagActive) then table.insert(mainButtons, {tag='button', attributes={id='btn_flag_toggle', active=flagActive, height='30', width='30', rectAlignment='MiddleLeft', image='ui_flag', offsetXY=mainButtonX..' 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_flag', visibility=PERMEDIT}}); mainButtonX = mainButtonX + 30; end;\n";
        	}
        	if ($input['MODULE_MOVEMENT']) {
        		$res .= "table.insert(mainButtons, {tag='button', attributes={id='btn_move_toggle', active=moveActive, height='30', width='30', rectAlignment='MiddleLeft', image='ui_splitpath', offsetXY=mainButtonX..' 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_move', visibility=PERMEDIT}});\n";
        		$res .= "mainButtonX = mainButtonX + 30;\n";
        	}
        	if (($input['MODULE_FLAG'] && !$input['LOCK_FLAG']) || $input['MODULE_BARS'] || $input['MODULE_MARKERS'] || ($input['MODULE_GEOMETRY'] && !$input['LOCK_GEOMETRY'])) {
        		$theSettingsButton = "table.insert(mainButtons, {tag='button', attributes={height='30', width='30', rectAlignment='MiddleRight', image='ui_gear', offsetXY='-50 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(0)', visibility=PERMEDIT}});\n";
        		if ($input['MODULE_GEOMETRY'] && !$input['LOCK_GEOMETRY']) { $theSettingsButton = "table.insert(mainButtons, {tag='button', attributes={height='30', width='30', rectAlignment='MiddleRight', image='ui_gear', offsetXY='-50 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(geometry)', visibility=PERMEDIT}});\n"; }
        		if ($input['MODULE_FLAG'] && !$input['LOCK_FLAG']) { $theSettingsButton = "table.insert(mainButtons, {tag='button', attributes={height='30', width='30', rectAlignment='MiddleRight', image='ui_gear', offsetXY='-50 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(flag)', visibility=PERMEDIT}});\n"; }
        		if ($input['MODULE_BARS']) { $theSettingsButton = "table.insert(mainButtons, {tag='button', attributes={height='30', width='30', rectAlignment='MiddleRight', image='ui_gear', offsetXY='-50 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(bars)', visibility=PERMEDIT}});\n"; }
        		if ($input['MODULE_MARKERS']) { $theSettingsButton = "table.insert(mainButtons, {tag='button', attributes={height='30', width='30', rectAlignment='MiddleRight', image='ui_gear', offsetXY='-50 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(markers)', visibility=PERMEDIT}});\n"; }
        		$res .= $theSettingsButton;
        	}
        	if ($input['MODULE_FLAG'] || $input['MODULE_MARKERS']) {
        		$res .= "table.insert(mainButtons, {tag='button', attributes={height='30', width='30', rectAlignment='MiddleRight', image='ui_reload', offsetXY='-20 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='rebuildUI', visibility=PERMVIEW}});\n";
        	}

        	//MAIN UI

        	$settingsChildren = [];
        	$mainChildren = [];
        	$settingsButtonX = 0;



        	if ($input['MODULE_FLAG']) {
        		$res .= "local activeFlag = (state.flag.image ~= nil and state.flag.image ~= '' and state.flag.height ~= nil and state.flag.width ~= nil and state.flag.height > 0 and state.flag.width > 0);\n";
        		$mainChildren[] = "(activeFlag and {tag='Panel', attributes={ id='flag_container', minHeight=(state.flag.height) * 100, active=(flag_active == true) }, children={ {tag='image', attributes={image='fl_image', width=((state.flag.width) * 100), color=state.flag.color or '#ffffff'}} } } or {})";

        		if (!$input['LOCK_FLAG']) {
        			$settingsChildren[] = "{tag='panel',attributes={id='ui_settings_flag',offsetXY='0 40',height='400',rectAlignment='LowerCenter',color='black',active=ui_mode=='flag'},
        				children={
        					{tag='VerticalLayout',attributes={width=640,height='340',spacing='5',rectAlignment='UpperCenter',offsetXY='0 -30',childForceExpandHeight=false,padding='5 5 5 5'},
        						children={
        							{tag='Text',attributes={text='URL',color='#ffffff',alignment='MiddleLeft',minHeight='20'}},
        							{tag='InputField',attributes={id='inp_flag_image',text=state.flag.image,onEndEdit='ui_editflag',minheight='30'}},
        							{tag='HorizontalLayout',attributes={childForceExpandHeight=false,spacing='5'},
        								children={
        									{tag='Text',attributes={text='Width',color='#ffffff',alignment='MiddleLeft',minheight='30',preferredWidth='50'}},
        									{tag='InputField',attributes={id='inp_flag_width',text=state.flag.width,onEndEdit='ui_editflag',minheight='30',preferredWidth='50'}},
        									{tag='Text',attributes={text='Height',color='#ffffff',alignment='MiddleLeft',minheight='30',preferredWidth='50',preferredWidth='50'}},
        									{tag='InputField',attributes={id='inp_flag_height',text=state.flag.height,onEndEdit='ui_editflag',minheight='30',preferredWidth='50'}}}},
        									{tag='HorizontalLayout',attributes={childForceExpandHeight=false,spacing='5'},children={{tag='Text',attributes={text='Color',color='#ffffff',alignment='MiddleLeft',minheight='30',preferredWidth='50',preferredWidth='50'}},
        									{tag='InputField',attributes={id='inp_flag_color',text=state.flag.color,onEndEdit='ui_editflag',minheight='30',preferredWidth='50'}},
        									{tag='Text',attributes={text='Auto-On',color='#ffffff',alignment='MiddleLeft',minheight='30',preferredWidth='50',preferredWidth='50'}},
        									{tag='Toggle',attributes={id='inp_flag_automode',onValueChanged='ui_editflag',minheight='30',isOn=state.flag.automode,preferredWidth='50'}}
        								}
        							}
        						}
        					},
        					{tag='text',attributes={fontSize='24',height='30',text='FLAG',color='#cccccc',rectAlignment='UpperLeft',alignment='MiddleCenter'}},
        					{tag='Button',attributes={width='150',height='30',rectAlignment='LowerRight',text='Remove Flag',onClick='ui_clearflag'}}
        				}
        			}";
        			$settingsChildren[] = "{tag='button', attributes={height='40', width='40', rectAlignment='LowerLeft', image='ui_flag', offsetXY='".$settingsButtonX." 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(flag)'}}";
        			$settingsButtonX += 40;
        		}
        	}

        	if ($input['MODULE_MARKERS']) {
        		$res .= "local mainlist_markers = {}\n";
        		$res .= "local settingslist_markers = {}\n";

        		$res .= "for i,marker in pairs(state.markers) do\n";
        			$res .= "table.insert(mainlist_markers,{tag='panel',attributes={},
        				children={
        					{tag='image',attributes={image=assetBuffer[marker[2]],color=marker[3],rectAlignment='LowerLeft',width='60',height='60'}},
        					{tag='text',attributes={id='counter_mk_'..i,text=marker[4]>1 and marker[4]or'',color='#ffffff',rectAlignment='UpperRight',width='20',height='20'}}
        				}
        			});\n";
        			$res .= "table.insert(settingslist_markers,{tag='panel',attributes={color='#cccccc'},
        				children={
        					{tag='image',attributes={width=90,height=90,image=assetBuffer[marker[2]],color=marker[3],rectAlignment='MiddleCenter'}},
        					{tag='text',attributes={id='disp_mk_'..i,width=30,height=30,fontSize=20,text=marker[4]>1 and marker[4]or'',rectAlignment='UpperLeft',alignment='MiddleLeft',offsetXY='5 0'}},
        					{tag='button',attributes={width=30,height=30,image='ui_close',rectAlignment='UpperRight',colors='black|#808080|#cccccc',alignment='UpperRight',onClick='ui_popmarker('..i..')'}},
        					{tag='text',attributes={width=110,height=30,rectAlignment='LowerCenter',resizeTextMinSize=10,resizeTextMaxSize=14,resizeTextForBestFit=true,fontStyle='Bold',text=marker[1],color='Black',alignment='LowerCenter'}}
        				}
        			});\n";
        		$res .= "end;\n";

        		$mainChildren[] = "{tag='GridLayout', attributes={contentSizeFitter='vertical', childAlignment='LowerLeft', flexibleHeight='0', cellSize='70 70', padding='20 20 0 0'}, children=mainlist_markers}";

        		$settingsChildren[] = "{tag='panel',attributes={id='ui_settings_markers', offsetXY='0 40', height='400', rectAlignment='LowerCenter', color='black', active=(ui_mode == 'markers')},
        			children={
        				{tag='VerticalScrollView',attributes={width=640,height='340',rotation='0.1 0 0',rectAlignment='UpperCenter',offsetXY='0 -30',color='transparent'},
        					children={
        						{tag='GridLayout',attributes={padding='6 6 6 6', cellSize='120 120', spacing='2 2', childForceExpandHeight='false', autoCalculateHeight='true'}, children=settingslist_markers}
        					}
        				},
        				{ tag='text', attributes={fontSize='24', height='30', text='MARKERS', color='#cccccc', rectAlignment='UpperLeft', alignment='MiddleCenter'}},
        				{ tag='Button', attributes={width='150', height='30', rectAlignment='LowerRight', text='Clear Markers', onClick='ui_clearmarkers'}},
        			}
        		}";
        		$settingsChildren[] = "{tag='button', attributes={height='40', width='40', rectAlignment='LowerLeft', image='ui_stack', offsetXY='".$settingsButtonX." 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(markers)'}}";
        		$settingsButtonX += 40;

        	}

        	if ($input['MODULE_BARS']) {
        		$res .= "local mainlist_bars = {}
        		local settingslist_bars = {{tag='Row',attributes={preferredHeight='30'},children={
        			{tag='Cell',children={{tag='Text',attributes={color='#cccccc',text='Name'}}}},
        			{tag='Cell',children={{tag='Text',attributes={color='#cccccc',text='Color'}}}},
        			{tag='Cell',children={{tag='Text',attributes={color='#cccccc',text='Current'}}}},
        			{tag='Cell',children={{tag='Text',attributes={color='#cccccc',text='Max'}}}},
        			{tag='Cell',children={{tag='Text',attributes={color='#cccccc',text='Text'}}}},
                    {tag='Cell',children={{tag='Text',attributes={color='#cccccc',text='Big'}}}},
        		}}}
        		for i,bar in pairs(state.bars) do
        			local per = ((bar[4] == 0) and 0 or (bar[3] / bar[4] * 100))
        			table.insert(mainlist_bars,
        	        {tag='horizontallayout', attributes={id='bar_container_'..i,minHeight=bar[6]and 30 or 15,childForceExpandWidth=false,childForceExpandHeight=false,childAlignment='MiddleCenter'},
        	            children={
        	                {tag='button', attributes={preferredHeight='20',preferredWidth='20',flexibleWidth='0',image='ui_minus',colors='#ccccccff|#ffffffff|#404040ff|#808080ff',onClick='ui_adjbar('..i..'|-1)',visibility=PERMEDIT} },
        	                {tag='panel', attributes={flexibleWidth='1',flexibleHeight='1'},
        	                    children={
        	                        {tag='progressbar', attributes={width='100%',height='100%',id='bar_'..i,color='#00000080',fillImageColor=bar[2],percentage=per,textColor='transparent'} },
        	                        {tag='text', attributes={id='bar_'..i..'_text',text=bar[3]..' / '..bar[4],active=bar[5]or false,color='#ffffff',fontStyle='Bold',outline='#000000',outlineSize='1 1'} }
        	                    }
        	                },
        	                {tag='button', attributes={preferredHeight='20',preferredWidth='20',flexibleWidth='0',image='ui_plus',colors='#ccccccff|#ffffffff|#404040ff|#808080ff',onClick='ui_adjbar('..i..'|1)',visibility=PERMEDIT} }
        	            }
        	        })
        			table.insert(settingslist_bars,
        			    {tag='Row', attributes={preferredHeight='30'},
        			        children={
        			            {tag='Cell',children={{tag='InputField',attributes={id='inp_bar_'..i..'_name',onEndEdit='ui_editbar',text=bar[1]or''}}}},
        			            {tag='Cell',children={{tag='InputField',attributes={id='inp_bar_'..i..'_color',onEndEdit='ui_editbar',text=bar[2]or'#ffffff'}}}},
        			            {tag='Cell',children={{tag='InputField',attributes={id='inp_bar_'..i..'_current',onEndEdit='ui_editbar',text=bar[3]or 10}}}},
        			            {tag='Cell',children={{tag='InputField',attributes={id='inp_bar_'..i..'_max',onEndEdit='ui_editbar',text=bar[4]or 10}}}},
                                {tag='Cell',children={{tag='Toggle',attributes={id='inp_bar_'..i..'_text',onValueChanged='ui_editbar',isOn=bar[5]or false}}}},
        			            {tag='Cell',children={{tag='Toggle',attributes={id='inp_bar_'..i..'_big',onValueChanged='ui_editbar',isOn=bar[6]or false}}}},
        			            {tag='Cell',children={{tag='Button',attributes={onClick='ui_removebar('..i..')',image='ui_close',colors='#cccccc|#ffffff|#808080'}}}}
        			        }
        			    })
        			end\n";
        		$mainChildren[] = "{tag='VerticalLayout', attributes={contentSizeFitter='vertical', childAlignment='LowerCenter', flexibleHeight='0'}, children=mainlist_bars}";

        		$settingsChildren[] = "{tag='panel', attributes={id='ui_settings_bars',offsetXY='0 40',height='400',rectAlignment='LowerCenter',color='black',active=ui_mode=='bars'},
        			children={
        				{tag='VerticalScrollView', attributes={width=640,height='340',rotation='0.1 0 0',rectAlignment='UpperCenter',color='transparent',offsetXY='0 -30'},
        					children={
        						{tag='TableLayout', attributes={columnWidths='0 100 60 60 30 30 30',childForceExpandHeight='false',cellBackgroundColor='transparent',autoCalculateHeight='true',padding='6 6 6 6'},
        							children=settingslist_bars
        						}
        					}
        				},
        				{tag='text', attributes={fontSize='24',height='30',text='BARS',color='#cccccc',rectAlignment='UpperLeft',alignment='MiddleCenter'} },
        				{tag='Button', attributes={width='150',height='30',rectAlignment='LowerLeft',text='Add Bar',onClick='ui_addbar'} },
        				{tag='Button', attributes={width='150',height='30',rectAlignment='LowerRight',text='Clear Bars',onClick='ui_clearbars'} }
        			}
        		}";
        		$settingsChildren[] = "{tag='button', attributes={height='40', width='40', rectAlignment='LowerLeft', image='ui_bars', offsetXY='".$settingsButtonX." 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(bars)'}}";
        		$settingsButtonX += 40;
        	}

        	if ($input['MODULE_GEOMETRY']) {
        		if (!$input['LOCK_GEOMETRY']) {
        			$settingsChildren[] = "{tag='panel',
        				attributes={id='ui_settings_geometry',offsetXY='0 40',height='400',rectAlignment='LowerCenter',color='black',active=ui_mode=='geometry'},
        				children={
        					{tag='VerticalLayout', attributes={width=640,height='340',spacing='5',rectAlignment='UpperCenter',offsetXY='0 -30',childForceExpandHeight=false,padding='5 5 5 5'},
        						children={
        							{tag='VerticalLayout', attributes={flexibleHeight=0,childForceExpandHeight=false},
        								children={
        									{tag='Text', attributes={color='#ffffff',text='Mesh URL',preferredHeight='20'} },
        									{tag='InputField', attributes={id='inp_geometry_mesh',text=state.geometry.mesh or'',onEndEdit='ui_editgeometry',preferredHeight='30'} }
        								}
        							},
        							{tag='VerticalLayout', attributes={flexibleHeight=0,childForceExpandHeight=false},
        								children={
        									{tag='Text', attributes={color='#ffffff',text='Texture URL',preferredHeight='20'} },
        									{tag='InputField', attributes={id='inp_geometry_texture',text=state.geometry.texture or'',onEndEdit='ui_editgeometry',preferredHeight='30'} }
        								}
        							},
        							{tag='VerticalLayout', attributes={flexibleHeight=0,childForceExpandHeight=false},
        								children={
        									{tag='Text', attributes={color='#ffffff',text='Normal URL',preferredHeight='20'} },
        									{tag='InputField', attributes={id='inp_geometry_texture',text=state.geometry.normal or'',onEndEdit='ui_editgeometry',preferredHeight='30'} }
        								}
        							},
        							{tag='VerticalLayout', attributes={flexibleHeight=0,childForceExpandHeight=false},
        								children={
        									{tag='Text', attributes={color='#ffffff',text='Material',preferredHeight='20'} },
        									{tag='HorizontalLayout', attributes={flexibleWidth=0,spacing=5,preferredHeight='30'},
        										children={
        											{tag='ToggleButton', attributes={id='inp_geometry_material_0',colors='#ffcc33|#ffffff|#808080',selectedBackgroundColor='#dddddd',deselectedBackgroundColor='#999999',isOn=state.geometry.material==0,onClick='ui_editgeometry(0)',text='Plastic'} },
        											{tag='ToggleButton', attributes={id='inp_geometry_material_1',colors='#ffcc33|#ffffff|#808080',selectedBackgroundColor='#dddddd',deselectedBackgroundColor='#999999',isOn=state.geometry.material==1,onClick='ui_editgeometry(1)',text='Wood'} },
        											{tag='ToggleButton', attributes={id='inp_geometry_material_2',colors='#ffcc33|#ffffff|#808080',selectedBackgroundColor='#dddddd',deselectedBackgroundColor='#999999',isOn=state.geometry.material==2,onClick='ui_editgeometry(2)',text='Metal'} },
        											{tag='ToggleButton', attributes={id='inp_geometry_material_3',colors='#ffcc33|#ffffff|#808080',selectedBackgroundColor='#dddddd',deselectedBackgroundColor='#999999',isOn=state.geometry.material==3,onClick='ui_editgeometry(3)',text='Cardboard'} }
        										}
        									}
        								}
        							},
        							{tag='VerticalLayout', attributes={flexibleHeight=0,preferredWidth=30,childForceExpandHeight=false},
        								children={
        									{tag='Text', attributes={color='#ffffff',text='Color',preferredHeight='20'} },
        									{tag='HorizontalLayout', attributes={flexibleWidth=0,preferredHeight='30',spacing=5,childForceExpandWidth=false},
        										children={
        											{tag='InputField',attributes={id='inp_geometry_color',text=state.geometry.color or'inherit',flexibleWidth='1',onEndEdit='ui_editgeometry'}},
        											{tag='Button',attributes={image='ui_share',preferredWidth='30',colors='White|White|#808080|#40404040',onClick='ui_geometry_setcolor(inherit)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='White|White|#808080|#40404040',onClick='ui_geometry_setcolor(#ffffff)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Brown|Brown|#808080|#40404040',onClick='ui_geometry_setcolor(#713b17)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Red|Red|#808080|#40404040',onClick='ui_geometry_setcolor(#da1918)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Orange|Orange|#808080|#40404040',onClick='ui_geometry_setcolor(#f4641d)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Yellow|Yellow|#808080|#40404040',onClick='ui_geometry_setcolor(#e7e52c)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Green|Green|#808080|#40404040',onClick='ui_geometry_setcolor(#31b32b)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Teal|Teal|#808080|#40404040',onClick='ui_geometry_setcolor(#21b19b)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Blue|Blue|#808080|#40404040',onClick='ui_geometry_setcolor(#1f87ff)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Purple|Purple|#808080|#40404040',onClick='ui_geometry_setcolor(#a020f0)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Pink|Pink|#808080|#40404040',onClick='ui_geometry_setcolor(#f570ce)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Black|Black|#808080|#40404040',onClick='ui_geometry_setcolor(#aaaaaa)'}},
        											{tag='Button',attributes={image='ui_drop',preferredWidth='30',colors='Grey|Grey|#808080|#40404040',onClick='ui_geometry_setcolor(#191919)'}}
        										}
        									}
        								}
        							}
        						}
        					},
        					{tag='text', attributes={fontSize='24',height='30',text='GEOMETRY',color='#cccccc',rectAlignment='UpperLeft',alignment='MiddleCenter'} },
        					{tag='Button', attributes={width='150',height='30',rectAlignment='LowerRight',text='Remove Geometry',onClick='ui_cleargeometry'} }
        				}
        			}";
        			$settingsChildren[] = "{tag='button', attributes={height='40', width='40', rectAlignment='LowerLeft', image='ui_cube', offsetXY='".$settingsButtonX." 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(geometry)'}}";
        			$settingsButtonX += 40;
        		}
        	}

        	$mainChildren[] = "{tag='Panel',attributes={minHeight='30',flexibleHeight='0'}, children=mainButtons }";


        	//OVERHEAD!
        	$res .= "local ui_overhead = { tag='Panel', attributes={childForceExpandHeight='false',visibility=PERMVIEW,position='0 0 ".($input['OVERHEAD_HEIGHT'] * -100)."',rotation=orient=='HORIZONTAL'and'0 0 0'or'-90 0 0',active=ui_mode=='0',scale='".($input['UI_SCALE'])." ".($input['UI_SCALE'])." ".($input['UI_SCALE'])."',height=0,color='red',width=w},
        			children={
        				{tag='VerticalLayout',attributes={rectAlignment='LowerCenter',childAlignment='LowerCenter',childForceExpandHeight=false,childForceExpandWidth=true,height='5000',spacing='5'},
        					children={".implode(",", $mainChildren)."}
        				}
        			}
        		}\n";

        	//SETTINGS!
        	if (!empty($settingsChildren)) {
        		$settingsChildren[] = "{tag='button', attributes={height='40', width='40', rectAlignment='LowerCenter', image='ui_close', offsetXY='0 0', colors='#ccccccff|#ffffffff|#404040ff|#808080ff', onClick='ui_setmode(0)'}}";
        		$res .= "local ui_settings = {tag='panel', attributes={id='ui_settings',height='0',width=640,position='0 0 ".($input['OVERHEAD_HEIGHT'] * -100)."',rotation=(orient=='HORIZONTAL'and'0 0 0'or'-90 0 0'),scale='".$input['UI_SCALE']." ".$input['UI_SCALE']." ".$input['UI_SCALE']."',active=(ui_mode ~= '0'),visibility=PERMEDIT},
        			children={".implode(",", $settingsChildren)."}
        		}\n";
        	} else {
        		$res .= "local ui_settings = {};\n";
        	}

        	//MOVEMENT!
        	$res .= "local ui_movement = {};\n";

        	if ($input['MODULE_MOVEMENT']) {

        		$ls_logic = "";
        		$ls_render = "";

        		if ($input['MOVEMENT']['MODE'] == 1 || $input['MOVEMENT']['MODE'] == 3) { // Simple Brackets or Complex Brackets
        			if ($input['MOVEMENT']['LANDSHOW']) {
        				$ls_logic = "local commitcolor = 'ffffff'\n";
        				if ($input['MOVEMENT']['LANDTEST']) {
        					$ls_logic = "local commitcolor = '00ff00';
        					local tpos = self.getPosition();
        					local trot = self.getRotation();
        					local t = rotateVector(move_store_pos, trot[2]);
        					local cast = Physics.cast({
        						origin = {x=tpos[1] + t[1] / 100, y=tpos[2], z=tpos[3] + t[2] / 100},
        						direction = {0,1,0},
        						max_distance = 0.5,
        						type = 3,
        						size = {".($input['BASE_WIDTH']).", 0.25, ".($input['BASE_LENGTH'])."},
        						orientation = {0, trot[2] + move_store_rot, 0},
        					});
        					for i,col in pairs(cast) do
        						if (col.hit_object ~= self) then
        							commitcolor = 'ff0000'
        						end
        					end\n";
        				}
        				$ls_render = "{tag='panel', attributes={visibility=PERMVIEW, rectAlignment='MiddleCenter', width=".($input['BASE_WIDTH']*100).", height=".($input['BASE_WIDTH']*100).", position=table.concat(displayPos, ' '), rotation='0 0 '..-rot}, children={{tag='panel', attributes={image='moveland', color='#'..commitcolor..'44', rectAlignment='MiddleCenter', position='0 0 ".($input['MOVEMENT']['UIHEIGHT']*100-1)."'}},}},";
        			}
        		}

        		if ($input['MOVEMENT']['MODE'] == 4) {
        			if ($input['MOVEMENT']['LANDSHOW']) {
        				$ls_logic = "local commitcolor = 'ffffff';
        					local curDef = nil
        					if (move_speed ~= 0) then
        						curDef = move_definitions[move_speed];
        					end\n";
        				if ($input['MOVEMENT']['LANDTEST']) {
        					$ls_logic = "local commitcolor = 'ffffff';
        						local curDef = nil
        						if (move_speed ~= 0) then
        							curDef = move_definitions[move_speed];
        							commitcolor='00ff00';
        							local tpos = self.getPosition();
        							local trot = self.getRotation();
        							local t = rotateVector({curDef[5], curDef[6], 0}, trot[2]);
        							local cast = Physics.cast({
        								origin = {x=tpos[1] + t[1], y=tpos[2], z=tpos[3] + t[2]},
        								direction = {0,1,0},
        								max_distance = 0.5,
        								type = 3,
        								size = {".$input['BASE_WIDTH'].", 0.25, ".$input['BASE_LENGTH']."},
        								orientation = {0, trot[2] + curDef[7], 0},
        							});
        							for i,col in pairs(cast) do
        								if (col.hit_object ~= self) then commitcolor = 'ff0000'
        								end
        							end
        						end\n";
        				}
        				$ls_render = "{tag='panel', attributes={image='moveland', visibility=PERMVIEW, color='#'..commitcolor..'44', rectAlignment='MiddleCenter', position=((curDef == nil) and 0 or (curDef[5]*100))..' '..((curDef == nil) and 0 or (curDef[6]*100))..' '..'".($input['MOVEMENT']['UIHEIGHT'] * 100 - 1)."', rotation='0 0 '..(curDef == nil and 0 or -curDef[7])}},";
        			}
        		}


        		if ($input['MOVEMENT']['MODE'] == 1) { //Simple Gauge
        			$res .= "if move_active then
        				local list = {}
        				move_store_pos = {0,0,0};
        				move_store_rot = 0;
        				local pos = {0,0,0};
        				local rot = 0;
        				local displayPos = {0,0,0};
        				for i,v in pairs(move_speed) do
        					rot = rot + (v * ".($input['MOVEMENT']['TURNNOTCH']).")
        					local tmp = { tag='Panel', attributes={visibility=PERMEDIT, color='transparent', rectAlignment='MiddleCenter', width=".($input['BASE_WIDTH'] * 100).", height=".($input['MOVEMENT']['SPEEDDISTANCE'] * 100).", position=table.concat(pos, ' '), rotation='0 0 '..-rot},
        						children={
        							{tag='Image', attributes={color='#ffffff', image='movenode', width=".($input['BASE_WIDTH'] * 25).", height=".($input['BASE_LENGTH'] * 25).", rectAlignment='MiddleCenter'}},
        							{tag='Button', attributes={image='ui_arrow_l', width=(20), height=(40), onClick='ui_move_dec('..i..')', active=(v > ".($input['MOVEMENT']['TURNMAX'] * -1)."), rectAlignment='MiddleLeft'}},
        							{tag='Button', attributes={image='ui_arrow_r', width=(20), height=(40), onClick='ui_move_inc('..i..')', active=(v < ".($input['MOVEMENT']['TURNMAX'])."), rectAlignment='MiddleRight'}},
        						}
        					};
        					table.insert(list, tmp)
        					move_store_pos = pos
        					move_store_rot = rot
        					displayPos = pos
        					pos = rotateVector({pos[1], pos[2], pos[3]}, -rot)
        					pos = rotateVector({pos[1], pos[2] + ".($input['MOVEMENT']['SPEEDDISTANCE'] * 100).", pos[3]}, rot)
        				end
        				$ls_logic
        				ui_movement = {tag='Panel', attributes={position='0 0 ".($input['MOVEMENT']['UIHEIGHT']*-100)."', rectAlignment='MiddleCenter'},
        				children={
        					$ls_render
        					{tag='panel', attributes={width=".($input['BASE_WIDTH'] * 100).", height=".($input['BASE_LENGTH'] * 100).", position='0 0 0', rotation='0 0 0'},
        						children={
        							{tag='panel', attributes={visibility=PERMEDIT, rectAlignment='LowerCenter', width='120', height='80', offsetXY='0 -80'},
        								children={
        									{tag='Button', attributes={image='ui_plus', width=(40), height=(40), onClick='ui_move_faster', active=(#move_speed < ".($input['MOVEMENT']['SPEEDMAX'])."), rectAlignment='UpperCenter'}},
        									{tag='Button', attributes={image='ui_minus', width=(40), height=(40), onClick='ui_move_slower', active=(#move_speed > 1), rectAlignment='LowerCenter'}},
        									{tag='Button', attributes={image='ui_block', color='#ff0000', width=(40), height=(40), onClick='ui_move_cancel', rectAlignment='MiddleLeft'}},
        									{tag='Button', attributes={image='ui_check', color='#00ff00', width=(40), height=(40), onClick='ui_move_commit', rectAlignment='MiddleRight'}},
        								}
        							},
        						}
        					},table.unpack(list)
        				}
        			}
        			end\n";
        		}
        		if ($input['MOVEMENT']['MODE'] == 2) { //Restricted Radius
        			$res .= "if move_active then
        				ui_movement = {tag='Panel', attributes={position='0 0 ".($input['MOVEMENT']['UIHEIGHT']*-100)."', rectAlignment='MiddleCenter'},
        					children={
        						{tag='panel', attributes={rectAlignment='MiddleCenter', width=".($input['BASE_WIDTH']*100).", height=".($input['BASE_LENGTH']*100).", position='0 0 0', rotation='0 0 0'},
        							children={
        								{tag='panel', attributes={rectAlignment='LowerCenter', width='120', height='80', offsetXY='0 ".($input['BASE_WIDTH']*-50)."'},
        									children={
        										{tag='Button', attributes={image='ui_plus', width=(40), height=(40), onClick='ui_move_faster', active=(move_speed < ".($input['MOVEMENT']['SPEEDMAX'])."), rectAlignment='UpperCenter'}},
        										{tag='Button', attributes={image='ui_minus', width=(40), height=(40), onClick='ui_move_slower', active=(move_speed > ".($input['MOVEMENT']['SPEEDMIN'])."), rectAlignment='LowerCenter'}},
        										{tag='Button', attributes={image='ui_block', color='#ff0000', width=(40), height=(40), onClick='ui_move_cancel', rectAlignment='MiddleLeft'}},
        										{tag='Button', attributes={image='ui_check', color='#00ff00', width=(40), height=(40), onClick='ui_move_commit', rectAlignment='MiddleRight'}}
        									}
        								}
        							}
        						}
        					}
        				}
        			end\n";
        		}
        		if ($input['MOVEMENT']['MODE'] == 3) { // Complex Brackets
        			$res .= "if move_active then
        				local list = {}
        				move_store_pos = {0,0,0}
        				move_store_rot = 0
        				local pos = {0,0,0}
        				local rot = 0
        				local displayPos = {0,0,0}
        				for i,v in pairs(move_speed) do
        					local dist = move_segments[i][1]
        					angle = 0
        					if (v ~= 0) then
        						angle = move_segments[i][2][math.abs(v)]
        						if (v < 0) then angle = angle * -1 end
        					end
        					pos = rotateVector({pos[1], pos[2], pos[3]}, -rot)
        					pos = rotateVector({pos[1], pos[2] + dist * 100, pos[3]}, rot)
        					rot = rot + (angle)
        					local tmp = { tag='Panel', attributes={visibility=PERMEDIT, color='transparent', rectAlignment='MiddleCenter', width=".($input['BASE_WIDTH']*100).", height=".($input['BASE_LENGTH']*100).", position=table.concat(pos, ' '), rotation='0 0 '..-rot},
        						children={
        							{tag='Image', attributes={color='#ffffff', image='movenode', width=".($input['BASE_WIDTH']*25).", height=".($input['BASE_LENGTH']*25).", rectAlignment='MiddleCenter'}},
        							{tag='Button', attributes={image='ui_arrow_l', width=(20), height=(40), onClick='ui_move_dec('..i..')', active=(v > -#move_segments[i][2]), rectAlignment='MiddleLeft'}},
        							{tag='Button', attributes={image='ui_arrow_r', width=(20), height=(40), onClick='ui_move_inc('..i..')', active=(v < #move_segments[i][2]), rectAlignment='MiddleRight'}},
        						}
        					}
        					table.insert(list, tmp);
        					move_store_pos = pos
        					move_store_rot = rot
        					displayPos = pos
        				end
        				$ls_logic
        				ui_movement = { tag='Panel', attributes={position='0 0 ".($input['MOVEMENT']['UIHEIGHT']*-100)."', rectAlignment='MiddleCenter'},
        				children={
        					$ls_render
        					{tag='panel', attributes={width=".($input['BASE_WIDTH']*100).", height=".($input['BASE_LENGTH']*100).", position='0 0 0', rotation='0 0 0'},
        						children={
        							{tag='panel', attributes={visibility=PERMEDIT, rectAlignment='LowerCenter', width='120', height='80', offsetXY='0 -80'}, children={
        								{tag='Button', attributes={image='ui_plus', width=(40), height=(40), onClick='ui_move_faster', active=(#move_speed < move_segments[#move_segments][1]), rectAlignment='UpperCenter'}},
        								{tag='Button', attributes={image='ui_minus', width=(40), height=(40), onClick='ui_move_slower', active=(#move_speed > 1), rectAlignment='LowerCenter'}},
        								{tag='Button', attributes={image='ui_block', color='#ff0000', width=(40), height=(40), onClick='ui_move_cancel', rectAlignment='MiddleLeft'}},
        								{tag='Button', attributes={image='ui_check', color='#00ff00', width=(40), height=(40), onClick='ui_move_commit', rectAlignment='MiddleRight'}},
        							}},
        						}
        					},table.unpack(list)}
        				}
        			end\n";
        		}
        		if ($input['MOVEMENT']['MODE'] == 4) { // Pre-Defined
        			$res .= "if move_active then
        				local list = {};
        				local tmp = {};
        				for i,def in pairs(move_definitions) do
        					table.insert(list, {tag='Button',attributes={rectAlignment='UpperCenter',offsetXY = (def[3]*40)..' '..(def[4]*40),image=assetBuffer[def[2]],color=def[8],width=40,height=40,onClick='ui_move_select('..i..')'}})
        				end
        				$ls_logic
        				ui_movement = {tag='Panel', attributes={position='0 0 ".($input['MOVEMENT']['UIHEIGHT']*-100)."', rectAlignment='MiddleCenter'},
        					children={
        						{tag='panel', attributes={rectAlignment='MiddleCenter', width=".($input['BASE_WIDTH']*100).", height=".($input['BASE_LENGTH']*100).", position='0 0 0', rotation='0 0 0'},
        							children={
        								{tag='panel', attributes={visibility=PERMEDIT, rectAlignment='LowerCenter', width='80', height='40', offsetXY='0 -40'},
        									children={
        										{tag='Button', attributes={image='ui_block', color='#ff0000', width=(40), height=(40), onClick='ui_move_cancel', rectAlignment='MiddleLeft'}},
        										{tag='Button', attributes={image='ui_check', color='#00ff00', width=(40), height=(40), onClick='ui_move_commit', rectAlignment='MiddleRight'}},
        									}
        								},
        								{tag='Panel', attributes={visibility=PERMEDIT, rectAlignment='LowerCenter', width='0', height='0', offsetXY = '0 -40'},
        									children=list
        								},
        								$ls_render
        							}
        						}
        					}
        				}
        			end\n";
        		}
        	}

        	$res .= "self.UI.setXmlTable({ui_overhead, ui_settings, ui_movement});\n";

            $res .= "end\n";
            return $res;
        }

    }

}

?>
