<?php

    SetValue($_IPS['VARIABLE'], $_IPS['VALUE']);

    if ($_IPS['VALUE'] == false)
      {
        UNIFI_disable_wlan(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE'])), GetValueString(IPS_GetVariableIDByName("wlan_id", $_IPS['VARIABLE'])), true);
      } else
      {
        UNIFI_disable_wlan(IPS_GetParent(IPS_GetParent($_IPS['VARIABLE'])), GetValueString(IPS_GetVariableIDByName("wlan_id", $_IPS['VARIABLE'])), false);
      }

?>