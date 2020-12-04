<?php

    SetValue($_IPS['VARIABLE'], $_IPS['VALUE']);

    $VarInf = IPS_GetVariable($_IPS['VARIABLE']);
    
    If ($VarInf['VariableType'] == 0)
      {
        if ($_IPS['VALUE'] == false)
        {
          UNIFI_enable_portfwd(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE'])), GetValueString(IPS_GetVariableIDByName("portfwd_id", $_IPS['VARIABLE'])), false);
        } else
        {
          UNIFI_enable_portfwd(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE'])), GetValueString(IPS_GetVariableIDByName("portfwd_id", $_IPS['VARIABLE'])), true);
        }
      }

?>