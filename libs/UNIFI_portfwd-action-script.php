<?php

    SetValue($_IPS['VARIABLE'], $_IPS['VALUE']);

    $VarInf = IPS_GetVariable($_IPS['VARIABLE']);
    
    If ($VarInf['VariableType'] == 0)
      {
        if ($_IPS['VALUE'] == false)
        {
          UNIFI_disable_portfwd(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE'])), GetValueString(IPS_GetVariableIDByName("wlan_id", $_IPS['VARIABLE'])), true);
        } else
        {
          UNIFI_disable_portfwd(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE'])), GetValueString(IPS_GetVariableIDByName("wlan_id", $_IPS['VARIABLE'])), false);
        }
      }
    
    If ($VarInf['VariableType'] == 3)
      {
        UNIFI_set_portfwd_name(IPS_GetParent(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE']))), GetValueString(IPS_GetVariableIDByName("portfwd_id", IPS_GetParent($_IPS['VARIABLE']))), $_IPS['VALUE']);
      }
?>