<?php

    include __DIR__ . "/../libs/Client.php";

    class UniFiController extends IPSModule {
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();
 
            $this->RegisterPropertyString("url", "https://<Controller_IP>:8443");
            $this->RegisterPropertyString("username", "api_user");
            $this->RegisterPropertyString("password", "");
            $this->RegisterPropertyString("site", "default");
            $this->RegisterPropertyString("version", "5.12.35");
            $this->RegisterPropertyInteger("intervall", "60");

            $check = IPS_InstanceExists(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID));
            if ($check == false)
              {
                $InsID = IPS_CreateInstance("{485D0419-BE97-4548-AA9C-C083EB82E61E}");
                IPS_SetName($InsID, "WLAN");
                IPS_SetParent($InsID, $this->InstanceID);
              }

        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

            $this->RegisterVariableBoolean("online", "Online", "~Switch",1);

            $this->RegisterTimerUNIFI('Update', $this->ReadPropertyInteger('intervall'), 'UNIFI_readdata($id);');

            if (UNIFI_login_test($this->InstanceID) == "true")
			{
				$this->SetStatus(102);
            } else
            {
                $this->SetStatus(203);
			}
        }
 
	    protected function RegisterTimerUNIFI($ident, $interval, $script) {

		    $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

		    if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
		    	IPS_DeleteEvent($id);
		    	$id = 0;
		    }

		    if (!$id) {
			    $id = IPS_CreateEvent(1);
			    IPS_SetParent($id, $this->InstanceID);
		    	IPS_SetIdent($id, $ident);
		    }

		    IPS_SetName($id, $ident);
		    IPS_SetHidden($id, true);
		    IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");
		    if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");

		    if (!($interval > 0)) {
		    	IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
		    	IPS_SetEventActive($id, false);
		    } else {
		    	IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $interval);
		    	IPS_SetEventActive($id, true);
		    }
	    }

        public function disable_wlan($wlanid, $bool) {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->disable_wlan($wlanid, $bool);

            // return var_dump($results);
        }

        public function readdata() {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            ob_start();
            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();
            ob_end_clean();

            $wlan = $unifi_connection->list_wlanconf();
            foreach ($wlan as $nr => $test)
            {
                $check = IPS_VariableExists(@IPS_GetVariableIDByName($wlan[$nr]->name, @IPS_GetInstanceIDByName("WLAN", $this->InstanceID)));
                if ($check == false) 
                  {
                    $VarID = IPS_CreateVariable(0);
                    IPS_SetName($VarID, $wlan[$nr]->name);
                    IPS_SetParent($VarID, @IPS_GetInstanceIDByName("WLAN", $this->InstanceID));
                    IPS_SetVariableCustomProfile($VarID, "~Switch");
                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "wlan_id");
                    IPS_SetParent($VarID, @IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))));
                    SetValueString($VarID, $wlan[$nr]->_id);
                  } else
                  {
                    SetValueString(IPS_GetVariableIDByName("wlan_id", @IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID)))), $wlan[$nr]->_id);
                  }

                echo $wlan[$nr]->name;
                echo " ";
                echo $wlan[$nr]->_id;
                echo " ";
                echo $wlan[$nr]->x_passphrase ;
                echo " ";
                echo $wlan[$nr]->enabled ;
                echo "<br>";
            }

            if ($login == "bool(true)")
            {
                $result = "true";
                $id = IPS_GetVariableIDByName ("Online", $this->InstanceID);
                SetValueBoolean($id, true);
            } else
            {
                $result = "false";
                $id = IPS_GetVariableIDByName ("Online", $this->InstanceID);
                SetValueBoolean($id, false);
            }

            return $result;
        }

        public function login_test() {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            ob_start();
            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();
            ob_end_clean();

            if ($login == "bool(true)")
            {
                $result = "true";
                $id = IPS_GetVariableIDByName ("Online", $this->InstanceID);
                SetValueBoolean($id, true);
            } else
            {
                $result = "false";
                $id = IPS_GetVariableIDByName ("Online", $this->InstanceID);
                SetValueBoolean($id, false);
            }

            return $result;
        }

        public function list_wlanconf() {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->list_wlanconf();

            return $results;
        }


    }

?>