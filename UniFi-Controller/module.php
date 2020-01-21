<?php

    include __DIR__ . "/../libs/Client.php";

    class UniFiController extends IPSModule {
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();
 
            $this->RegisterPropertyString("url", "https://192.168.1.7:8443");
            $this->RegisterPropertyString("username", "api_user");
            $this->RegisterPropertyString("password", "");
            $this->RegisterPropertyString("site", "default");
            $this->RegisterPropertyString("version", "5.12.35");
            $this->RegisterPropertyInteger("intervall", "120");

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
 
        public function DeleteObject($ObjectId) {
            $Object     = IPS_GetObject($ObjectId);
            $ObjectType = $Object['ObjectType'];
            switch ($ObjectType) {
            case 0: // Category
                DeleteCategory($ObjectId);
                break;
            case 1: // Instance
                EmptyCategory($ObjectId);
                IPS_DeleteInstance($ObjectId);
                break;
            case 2: // Variable
                IPS_DeleteVariable($ObjectId);
                break;
            case 3: // Script
                IPS_DeleteScript($ObjectId, false);
                break;
            case 4: // Event
                IPS_DeleteEvent($ObjectId);
                break;
            case 5: // Media
                IPS_DeleteMedia($ObjectId, true);
                break;
            case 6: // Link
                IPS_DeleteLink($ObjectId);
                break;
            default:
            Error ("Found unknown ObjectType $ObjectType");
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

            /*foreach ($wlanidsuc as $nr2 => $test2)
            {
                $exist = array_search($wlanidsuc[$nr2], $wlanids, true);
                echo $wlanids[$nr2]." ".$exist."\n";
            }*/

            foreach ($wlan as $nr => $test)
            {
                $check = IPS_VariableExists(@IPS_GetVariableIDByName($wlan[$nr]->name, @IPS_GetInstanceIDByName("WLAN", $this->InstanceID)));
                if ($check == false) 
                  {
                    $VarID = IPS_CreateVariable(0);
                    IPS_SetName($VarID, $wlan[$nr]->name);
                    IPS_SetParent($VarID, IPS_GetInstanceIDByName("WLAN", $this->InstanceID));
                    IPS_SetVariableCustomProfile($VarID, "~Switch");
                    SetValueBoolean($VarID, $wlan[$nr]->enabled);

                    copy(IPS_GetKernelDir()."modules/Symcon-UniFi/libs/UNIFI_wlan-action-script.php", IPS_GetKernelDir()."scripts/UNIFI_wlan-action-script.php");
                    $ScriptID = IPS_CreateScript(0);
                    IPS_SetParent ($ScriptID, $VarID);
                    IPS_SetName($ScriptID, "wlan-action-script");
                    IPS_SetHidden($ScriptID, true);
                    IPS_SetScriptFile($ScriptID, "UNIFI_wlan-action-script.php");
                    IPS_SetVariableCustomAction($VarID, $ScriptID);

                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "wlan_id");
                    IPS_SetParent($VarID, IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))));
                    SetValueString($VarID, $wlan[$nr]->_id);
                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "Passphrase");
                    IPS_SetParent($VarID, IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))));
                    SetValueString($VarID, $wlan[$nr]->x_passphrase);
                  } else
                  {
                    SetValueString(IPS_GetVariableIDByName("wlan_id", IPS_GetVariableIDByName($wlan[$nr]->name,(IPS_GetInstanceIDByName("WLAN", $this->InstanceID)))), $wlan[$nr]->_id);
                    SetValueBoolean(IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))), $wlan[$nr]->enabled);
                    SetValueString(IPS_GetVariableIDByName("Passphrase", IPS_GetVariableIDByName($wlan[$nr]->name,(IPS_GetInstanceIDByName("WLAN", $this->InstanceID)))), $wlan[$nr]->x_passphrase);
                  }
            }

            $wlanids = array();
            $varids = IPS_GetChildrenIDs(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID));
            foreach ($varids as $nr => $test)
            {
                $id = IPS_GetVariableIDByName("wlan_id", $varids[$nr]);
                $check = GetValueString($id);
                array_push($wlanids,$check);
            }
      
            $wlanidsuc = array();
            foreach ($wlan as $nr => $test)
            {
                array_push($wlanidsuc, $wlan[$nr]->_id);
            }
      
            $exist = array_diff($wlanids, $wlanidsuc);
            if (empty($exist) == false) 
            {
                foreach($exist as $nr => $test)
                {
                    echo $exist[$nr]." exist.\n";
                    foreach ($varids as $nr2 => $test)
                    {
                        $id = IPS_GetParent(IPS_GetVariableIDByName("wlan_id", $varids[$nr2]));
                        echo $id." id1 \n";
                        if ($exist[$nr] == $check) 
                        {
                            $children = IPS_GetChildrenIDs($id);
                            echo $id." id2 \n";
                            foreach ($children as $nr3 => $test)
                            {
                                echo $children[$nr3]." Children \n";
                                UNIFI_DeleteObject($children[$nr3]);
                            }
                            UNIFI_DeleteObject($id);
                        }
                    }
                }
            }
      
            var_dump($exist);


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