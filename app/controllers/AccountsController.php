<?php
/**
 * Accounts Controller
 */
class AccountsController extends Controller
{
    /**
     * Process
     */
    public function process()
    {
        $AuthUser = $this->getVariable("AuthUser");
        $EmailSettings = \Controller::model("GeneralData", "email-settings");

        // Auth
        if (!$AuthUser){
            header("Location: ".APPURL."/login");
            exit;
        } else if (
            !$AuthUser->isAdmin() && 
            !$AuthUser->isEmailVerified() &&
            $EmailSettings->get("data.email_verification")) 
        {
            header("Location: ".APPURL."/profile?a=true");
            exit;
        } else if ($AuthUser->isExpired()) {
            header("Location: ".APPURL."/expired");
            exit;
        }

        // Get accounts
        $Accounts = Controller::model("Accounts");
            $Accounts->setPageSize(8)
                     ->setPage(Input::get("page"))
                     ->where("user_id", "=", $AuthUser->get("id"))
                     ->orderBy("id","DESC")
                     ->fetchData();

        $this->setVariable("Accounts", $Accounts);

        $accpics = (array) $AuthUser->get("data.accpics");

        $changed = false;
        
        foreach($Accounts->getDataAs("Account") as $acc){
		
            if(!isset($accpics[$acc->get("username")])){
                $accpics[$acc->get("username")] = $this->getAccountPicture($acc->get("username"));
                $changed = true;
            }

        }

        if($changed){
            $AuthUser->set("data.accpics", $accpics)->save();
        }
        
        if (Input::post("action") == "remove") {
            $this->remove();
        }

        $this->view("accounts");
    }
	
	 private function getAccountPicture($accountname)
    {

        $instagramname = $accountname;

        $curl = curl_init();

        $s = array(
            CURLOPT_URL => "https://www.instagram.com/" . $instagramname,
            CURLOPT_REFERER => "https://google.com",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36",
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        );

        curl_setopt_array($curl, $s);
        $response = curl_exec($curl);
        curl_close($curl);

        $regex = '@<meta property="og:image" content="(.*?)"@si';
        preg_match_all($regex, $response, $return);

        if(isset($return[1][0])){

            $ret = $return[1][0];
        
        }else{
            $ret = null;
        }
        
        return $ret;

    }

    /**
     * Remove Account
     * @return void
     */
    private function remove()
    {   
        $this->resp->result = 0;
        $AuthUser = $this->getVariable("AuthUser");

        if (!Input::post("id")) {
            $this->resp->msg = __("ID is requred!");
            $this->jsonecho();
        }

        $Account = Controller::model("Account", Input::post("id"));

        if (!$Account->isAvailable() ||
            $Account->get("user_id") != $AuthUser->get("id")) 
        {
            $this->resp->msg = __("Invalid ID");
            $this->jsonecho();
        }

        // Delete instagram session data
        delete(APPPATH . "/sessions/" 
                       . $AuthUser->get("id") 
                       . "/" 
                       . $Account->get("username"));

        $Account->delete();
        
        $this->resp->result = 1;
        $this->jsonecho();
    }
}