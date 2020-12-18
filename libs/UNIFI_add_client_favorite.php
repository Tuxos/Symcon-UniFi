<?php

    $scriptid = IPS_GetVariable($_IPS['SELF']);
    $clientid = IPS_GetParent($scriptid);
    $childs = IPS_GetChildrenIDs($clientid);
    $favid = IPS_GetVariableIDByName("Favorites", IPS_GetParent(IPS_GetParent($clientid)));

    $name = GetValueString($clientid);
    $macadresse = GetValueString(IPS_GetVariableIDByName("MAC Adresse", $clientid));
    $anbindung =  GetValueBoolean(IPS_GetVariableIDByName("Anbindung", $clientid));
    
    // Erstelle Favorit

    $VarID = IPS_CreateVariable(3);
    IPS_SetName($VarID, $name);
    IPS_SetParent($VarID, IPS_GetInstanceIDByName("Clients", $this->InstanceID));
    if (empty($clients[$nr]->name) == false) { SetValueString($VarID, $clients[$nr]->name); } else { if (empty($clients[$nr]->hostname) == false) { SetValueString($VarID, $clients[$nr]->hostname); } else { SetValueString($VarID, "Kein Wert gesetzt"); } }
    $sort = explode(".", $clients[$nr]->ip);
    $komp = $sort[2].$sort[3];
    IPS_SetPosition($VarID, $komp);

    $VarID = IPS_CreateVariable(3);
    IPS_SetName($VarID, "MAC Adresse");
    IPS_SetParent($VarID, IPS_GetVariableIDByName($clients[$nr]->ip,(IPS_GetInstanceIDByName("Clients", $this->InstanceID))));
    SetValueString($VarID, $clients[$nr]->mac);
    IPS_SetPosition($VarID, 1);

    $VarID = IPS_CreateVariable(0);
    IPS_SetName($VarID, "Anbindung");
    IPS_SetParent($VarID, IPS_GetVariableIDByName($clients[$nr]->ip,(IPS_GetInstanceIDByName("Clients", $this->InstanceID))));
    SetValueBoolean($VarID, $clients[$nr]->is_wired);
    IPS_SetVariableCustomProfile($VarID, "UNIFI.Kabel");
    IPS_SetPosition($VarID, 2);
    if ($clients[$nr]->is_wired != 1)
    {
        $VarID = IPS_CreateVariable(3);
        IPS_SetName($VarID, "WLAN");
        IPS_SetParent($VarID, IPS_GetVariableIDByName($clients[$nr]->ip,(IPS_GetInstanceIDByName("Clients", $this->InstanceID))));
        SetValueString($VarID, $clients[$nr]->essid);
        IPS_SetPosition($VarID, 3);
    }

    $VarID = IPS_CreateVariable(0);
    IPS_SetName($VarID, "Blocked");
    IPS_SetParent($VarID, IPS_GetVariableIDByName($clients[$nr]->ip,(IPS_GetInstanceIDByName("Clients", $this->InstanceID))));
    IPS_SetVariableCustomProfile($VarID, "~Switch");
    SetValueBoolean($VarID, false);
    IPS_SetPosition($VarID, 5); 
    copy(IPS_GetKernelDir()."modules/Symcon-UniFi/libs/UNIFI_client-action-script.php", IPS_GetKernelDir()."scripts/UNIFI_client-action-script.php");
    $ScriptID = IPS_CreateScript(0);
    IPS_SetParent ($ScriptID, IPS_GetParent($VarID));
    IPS_SetName($ScriptID, "client-action-script");
    IPS_SetHidden($ScriptID, true);
    IPS_SetScriptFile($ScriptID, "UNIFI_client-action-script.php");
    IPS_SetVariableCustomAction($VarID, $ScriptID);                    
    IPS_SetPosition($ScriptID, 9);
    
?>