<?php

    SetValue($_IPS['VARIABLE'], $_IPS['VALUE']);

    $VarInf = IPS_GetVariable($_IPS['VARIABLE']);
    
    If ($VarInf['VariableType'] == 0)
      {
        if ($_IPS['VALUE'] == false)
        {
    UNIFI_unblock_client(IPS_GetParent(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE']))), GetValueString(IPS_GetVariableIDByName("MAC Adresse", IPS_GetParent($_IPS['VARIABLE']))));
        } else
        {
    UNIFI_block_client(IPS_GetParent(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE']))), GetValueString(IPS_GetVariableIDByName("MAC Adresse", IPS_GetParent($_IPS['VARIABLE']))));
        }
      }
    
//    If ($VarInf['VariableType'] == 3)
//      {
//  UNIFI_set_portfwd_name(IPS_GetParent(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE']))), GetValueString(IPS_GetVariableIDByName("portfwd_id", IPS_GetParent($_IPS['VARIABLE']))), $_IPS['VALUE']);
     // }
?>