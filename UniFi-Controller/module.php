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
                IPS_SetPosition($InsID, 2);
              }

            $check = IPS_InstanceExists(@IPS_GetInstanceIDByName("Portforward", $this->InstanceID));
            if ($check == false)
              {
                $InsID = IPS_CreateInstance("{485D0419-BE97-4548-AA9C-C083EB82E61E}");
                IPS_SetName($InsID, "Portforward");
                IPS_SetParent($InsID, $this->InstanceID);
                IPS_SetPosition($InsID, 3);
              }

            $check = IPS_InstanceExists(@IPS_GetInstanceIDByName("Clients", $this->InstanceID));
            if ($check == false)
              {
                $InsID = IPS_CreateInstance("{485D0419-BE97-4548-AA9C-C083EB82E61E}");
                IPS_SetName($InsID, "Clients");
                IPS_SetParent($InsID, $this->InstanceID);
                IPS_SetPosition($InsID, 4);
              }
            
            $check = IPS_VariableProfileExists("UNIFI.Kabel");
            if ($check == false)
            {
                IPS_CreateVariableProfile("UNIFI.Kabel", 0);
                IPS_SetVariableProfileAssociation("UNIFI.Kabel", false, "WLAN", "", -1);
                IPS_SetVariableProfileAssociation("UNIFI.Kabel", true, "Kabel", "", -1);
            }

            $check = IPS_VariableProfileExists("UNIFI.Online");
            if ($check == false)
            {
                IPS_CreateVariableProfile("UNIFI.Online", 0);
                IPS_SetVariableProfileAssociation("UNIFI.Online", false, "Offline", "", 16711680);
                IPS_SetVariableProfileAssociation("UNIFI.Online", true, "Online", "", 65280);
            }

        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

            $this->RegisterVariableBoolean("online", "Online", "~Switch",1);

            $id = $this->RegisterTimerUNIFI('Update', $this->ReadPropertyInteger('intervall'), 'UNIFI_readdata($id);');
            IPS_SetPosition($id, 9);

            // setze Status in der Instanzkonfiguration
            if (UNIFI_login_test($this->InstanceID) == "true")
			{
				$this->SetStatus(102);
            } else
            {
                $this->SetStatus(203);
			}
        }
 
        // ### Lösche beliebige Objekte mit $ObejctId ###
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
                IPS_DeleteScript($ObjectId, true);
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
            Error ("Found unknown ObjectType $ObjectType. Cannot delete.");
          }
        } 

        // ### Erstelle zyklischen Timer ###
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
            return $id;
	    }

        // ### Aktiviere/Deaktiviere WLAN ###
        public function disable_wlan($wlanid, $bool) {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->disable_wlan($wlanid, $bool);

        }

        // ### Auslesen der Grundkonfiguration für WLAN & Portforwarding und ob API Zugriff möglich. Wird zyklisch aufgerufen. ###
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

            // Erstelle (falls noch nicht vorhanden) die WLANs in IPSymcon. Falls schon vorhanden aktualisiere sie.
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
                    IPS_SetPosition($ScriptID, 2);

                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "wlan_id");
                    IPS_SetParent($VarID, IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))));
                    SetValueString($VarID, $wlan[$nr]->_id);
                    IPS_SetHidden($VarID, true);
                    IPS_SetPosition($VarID, 0);
                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "Passphrase");
                    IPS_SetParent($VarID, IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))));
                    SetValueString($VarID, $wlan[$nr]->x_passphrase);
                    IPS_SetPosition($VarID, 1);
                    IPS_SetVariableCustomAction($VarID, $ScriptID);
                  } else // update bestehende Variablen
                  {
                    SetValueString(IPS_GetVariableIDByName("wlan_id", IPS_GetVariableIDByName($wlan[$nr]->name,(IPS_GetInstanceIDByName("WLAN", $this->InstanceID)))), $wlan[$nr]->_id);
                    SetValueBoolean(IPS_GetVariableIDByName($wlan[$nr]->name,(@IPS_GetInstanceIDByName("WLAN", $this->InstanceID))), $wlan[$nr]->enabled);
                    SetValueString(IPS_GetVariableIDByName("Passphrase", IPS_GetVariableIDByName($wlan[$nr]->name,(IPS_GetInstanceIDByName("WLAN", $this->InstanceID)))), $wlan[$nr]->x_passphrase);
                  }
            }

            // Lösche im Controller nicht mehr vorhandene WLANs
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
                    foreach ($varids as $nr2 => $test)
                    {
                        $id = IPS_GetParent(IPS_GetVariableIDByName("wlan_id", $varids[$nr2]));
                        $idchild = GetValueString(IPS_GetVariableIDByName("wlan_id", $id));
                        if ($exist[$nr] == $idchild)                      
                        {
                            $children = IPS_GetChildrenIDs($id);
                            foreach ($children as $nr3 => $test)
                            {
                                UNIFI_DeleteObject($this->InstanceID, $children[$nr3]);
                            }
                            UNIFI_DeleteObject($this->InstanceID, $id);
                        }
                    }
                }
            }





            $portfwd = $unifi_connection->list_portforwarding();

            // Erstelle (falls noch nicht vorhanden) die Portforwards in IPSymcon. Falls schon vorhanden aktualisiere sie.
            foreach ($portfwd as $nr => $test)
            {
                $check = IPS_VariableExists(@IPS_GetVariableIDByName($portfwd[$nr]->name, @IPS_GetInstanceIDByName("Portforwards", $this->InstanceID)));
                if ($check == false) 
                  {
                    $VarID = IPS_CreateVariable(0);
                    IPS_SetName($VarID, $portfwd[$nr]->name);
                    IPS_SetParent($VarID, IPS_GetInstanceIDByName("Portforwards", $this->InstanceID));
                    IPS_SetVariableCustomProfile($VarID, "~Switch");
                    SetValueBoolean($VarID, $portfwd[$nr]->enabled);

                    copy(IPS_GetKernelDir()."modules/Symcon-UniFi/libs/UNIFI_wlan-action-script.php", IPS_GetKernelDir()."scripts/UNIFI_wlan-action-script.php");
                    $ScriptID = IPS_CreateScript(0);
                    IPS_SetParent ($ScriptID, $VarID);
                    IPS_SetName($ScriptID, "wlan-action-script");
                    IPS_SetHidden($ScriptID, true);
                    IPS_SetScriptFile($ScriptID, "UNIFI_wlan-action-script.php");
                    IPS_SetVariableCustomAction($VarID, $ScriptID);                    
                    IPS_SetPosition($ScriptID, 2);

                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "portfwd_id");
                    IPS_SetParent($VarID, IPS_GetVariableIDByName($portfwd[$nr]->name,(@IPS_GetInstanceIDByName("Portforwards", $this->InstanceID))));
                    SetValueString($VarID, $portfwd[$nr]->_id);
                    IPS_SetHidden($VarID, true);
                    IPS_SetPosition($VarID, 0);
                    $VarID = IPS_CreateVariable(3);
                    IPS_SetName($VarID, "dest_ip");
                    IPS_SetParent($VarID, IPS_GetVariableIDByName($portfwd[$nr]->name,(@IPS_GetInstanceIDByName("Portforwards", $this->InstanceID))));
                    SetValueString($VarID, $portfwd[$nr]->fwd);
                    IPS_SetPosition($VarID, 1);
                    IPS_SetVariableCustomAction($VarID, $ScriptID);
                  } else // update bestehende Variablen
                  {
                    SetValueString(IPS_GetVariableIDByName("portfwd_id", IPS_GetVariableIDByName($portfwd[$nr]->name,(IPS_GetInstanceIDByName("Portforwards", $this->InstanceID)))), $portfwd[$nr]->_id);
                    SetValueBoolean(IPS_GetVariableIDByName($portfwd[$nr]->name,(@IPS_GetInstanceIDByName("Portforwards", $this->InstanceID))), $portfwd[$nr]->enabled);
                    SetValueString(IPS_GetVariableIDByName("dest_ip", IPS_GetVariableIDByName($portfwd[$nr]->name,(IPS_GetInstanceIDByName("Portforwards", $this->InstanceID)))), $portfwd[$nr]->fwd);
                  }
            }

            // Lösche im Controller nicht mehr vorhandene WLANs
            $portfwdids = array();
            $varids = IPS_GetChildrenIDs(@IPS_GetInstanceIDByName("Portforwards", $this->InstanceID));
            foreach ($varids as $nr => $test)
            {
                $id = IPS_GetVariableIDByName("portfwd_id", $varids[$nr]);
                $check = GetValueString($id);
                array_push($portfwdids,$check);
            } 
            $portfwdidsuc = array();
            foreach ($portfwd as $nr => $test)
            {
                array_push($portfwdidsuc, $portfwd[$nr]->_id);
            }
            $exist = array_diff($portfwdids, $portfwdidsuc);
            if (empty($exist) == false) 
            {
                foreach($exist as $nr => $test)
                {
                    foreach ($varids as $nr2 => $test)
                    {
                        $id = IPS_GetParent(IPS_GetVariableIDByName("portfwd_id", $varids[$nr2]));
                        $idchild = GetValueString(IPS_GetVariableIDByName("portfwd_id", $id));
                        if ($exist[$nr] == $idchild)                      
                        {
                            $children = IPS_GetChildrenIDs($id);
                            foreach ($children as $nr3 => $test)
                            {
                                UNIFI_DeleteObject($this->InstanceID, $children[$nr3]);
                            }
                            UNIFI_DeleteObject($this->InstanceID, $id);
                        }
                    }
                }
            }









            // Login möglich oder nicht möglich - return Ausgabe der Funktion
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

        // ### Erstelle/Lösche Einträge in der Client Liste ###
        public function readdata_clients() {

            $clients = UNIFI_list_clients($this->InstanceID);

            // Lösche alle Clients
            
            $allclients = IPS_GetChildrenIDs(@IPS_GetObjectIDByName("Clients", $this->InstanceID));
            foreach ($allclients as $count => $test)
                            {
                              $clientchilds = IPS_GetChildrenIDs($allclients[$count]);
                              foreach ($clientchilds as $count2 => $test2)
                              {
                                UNIFI_DeleteObject($this->InstanceID, $clientchilds[$count2]);
                              }
                              UNIFI_DeleteObject($this->InstanceID, $allclients[$count]);
                            }

            // Erstelle die Clients in IPSymcon.
            foreach ($clients as $nr => $test)
            {
             if (empty($clients[$nr]->ip) == false)
             {
               $VarID = IPS_CreateVariable(3);
               IPS_SetName($VarID, $clients[$nr]->ip);
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

               $VarID = IPS_CreateVariable(3);
               IPS_SetName($VarID, "Zuletzt gesehen");
               IPS_SetParent($VarID, IPS_GetVariableIDByName($clients[$nr]->ip,(IPS_GetInstanceIDByName("Clients", $this->InstanceID))));
               $lastseen = date("H:i:s", $clients[$nr]->last_seen);
               SetValueString($VarID, $lastseen);
               IPS_SetPosition($VarID, 4);
             }
            }

        }


        // ### Prüfe ob Login möglich ###
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

        // ### Liste WLAN Konfiguration auf ###
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

        // ### Ändere WLAN Passphrase ###
        public function set_wlansettings($wlanid , $passphrase) {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->set_wlansettings($wlanid, $passphrase);

            return $results;
        }

        // ### Liste alle Unifi Devices auf ###
        public function list_devices() {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->list_devices();

            return $results;
        }
        
        // ### Liste alle online Clients auf ###
        public function list_clients() {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->list_clients();

            return $results;
        }
	   
        // ### Liste alle Portforwards auf ###
        public function list_portforwarding() {

            $url = $this->ReadPropertyString("url");
            $username = $this->ReadPropertyString("username");
            $password = $this->ReadPropertyString("password");
            $site = $this->ReadPropertyString("site");
            $version = $this->ReadPropertyString("version");

            $unifi_connection = new UniFi_API\Client($username, $password, $url, $site, $version, false);
            $login = $unifi_connection->login();

            $results = $unifi_connection->list_portforwarding();

            return $results;
	}

    }

?>