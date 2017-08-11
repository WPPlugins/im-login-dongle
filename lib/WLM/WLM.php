<?php
/*

MSN class ver 1.12pre by Tommy Wu
License: GPL

You can find MSN protocol from this site: http://msnpiki.msnfanatic.com/index.php/Main_Page

This class only support MSNP18, it will ignore any protocol parameter in MSN class.
The PHP module needed: curl pcre mcrypt bcmath

Sameple Code:

$msn = new MSN;

if (!$msn->connect('YOUR_MSN_ID', 'YOUR_MSN_PASSWORD')) {
    echo "Error for connect to MSN network\n";
    echo "$msn->error\n";
    exit;
}

$msn->sendMessage('Now: '.strftime('%m/%d/%y %H:%M:%S')."\nTesting\nSecond Line\n\n\n\nand Empty Line",
                  array(
                    'somebody1@hotmail.com',
                    'somebody2@hotmail.com'
                       )
                 );
echo "Done!\n";
exit;

*/

class MSN
{
    var $server = 'messenger.hotmail.com';
    var $port = 1863;

    var $passport_url = 'https://login.live.com/RST.srf';

    var $protocol = 'MSNP18';
    var $prod_key = 'C1BX{V4W}Q3*10SM';
    var $prod_id = 'PROD0120PW!CCV9@';
    var $login_method = 'SSO';
    var $application_id = 'CFE80F9D-180F-4399-82AB-413F33A1FA11';
    var $machine_guid = '{661896D6-FA40-46C9-88F1-C512CD5CA875}';

    var $oslang = '0x0409';
    var $osname = 'winnt';
    var $osver = '6.1.1';
    var $osplatform = 'i386';
    var $clientname = 'MSMSGS';
    var $clientver = '14.0.8117.416';

    // clientcapab, this value will be updated again in MSN(), so if you need to change this, you need to change the value defined in MSN()
    var $clientcapab = '';

    var $oim_maildata_url = 'https://rsi.hotmail.com/rsi/rsi.asmx';
    var $oim_maildata_soap = 'http://www.hotmail.msn.com/ws/2004/09/oim/rsi/GetMetadata';
    var $oim_read_url = 'https://rsi.hotmail.com/rsi/rsi.asmx';
    var $oim_read_soap = 'http://www.hotmail.msn.com/ws/2004/09/oim/rsi/GetMessage';
    var $oim_del_url = 'https://rsi.hotmail.com/rsi/rsi.asmx';
    var $oim_del_soap = 'http://www.hotmail.msn.com/ws/2004/09/oim/rsi/DeleteMessages';

    var $membership_url = 'https://contacts.msn.com/abservice/SharingService.asmx';
    var $membership_soap = 'http://www.msn.com/webservices/AddressBook/FindMembership';

    var $addmember_url = 'https://contacts.msn.com/abservice/SharingService.asmx';
    var $addmember_soap = 'http://www.msn.com/webservices/AddressBook/AddMember';

    var $addcontact_url = 'https://contacts.msn.com/abservice/abservice.asmx';
    var $addcontact_soap = 'http://www.msn.com/webservices/AddressBook/ABContactAdd';

    var $delmember_url = 'https://contacts.msn.com/abservice/SharingService.asmx';
    var $delmember_soap = 'http://www.msn.com/webservices/AddressBook/DeleteMember';

    var $id;
    var $fp = false;
    var $error = '';

    var $authed = false;
    var $user = '';
    var $password = '';

    var $passport_policy = '';
    var $oim_ticket = '';
    var $contact_ticket = '';

    // FIXME: even we login for following site, but... we don't need that now.
    var $web_ticket = '';
    var $sup_ticket = '';
    var $storage_ticket = '';

    // debug/log_file, this value will be updated again in MSN(), so if you need to change this, you need to change the value defined in MSN()
    var $debug = false;
    var $log_file = '';
    // timeout, this value will be updated again in MSN(), so if you need to change this, you need to change the value defined in MSN()
    var $timeout = 15;
    var $stream_timeout = 2;

    var $log_path = false;

    var $sb;
    var $sbid;
    var $font_fn = 'Arial';
    var $font_co = '0';
    var $font_ef = '';
    var $font_cs = '1';
    var $font_pf = '0';

    var $send_typing = false;
    var $windows = false;
    var $kill_me = false;

    // the message length (include header) is limited (maybe since WLM 8.5 released)
    // for WLM: 1664 bytes
    // for YIM: 518 bytes
    // for OIM: 573 bytes
    var $max_msn_message_len = 1664;
    var $max_yahoo_message_len = 518;
    var $max_oim_message_len = 573;

    function MSN($protocol = '', $debug = false, $timeout = 15, $client_capab = 0xA000800C, $extended_capab = 0x0)
    {
        if (is_string($debug) && $debug !== '') {
            $this->debug = true;
            $this->log_file = $debug;
        }
        else
            $this->debug = $debug;
        $this->timeout = $timeout;
        // check support
        if (!function_exists('curl_init')) die("We need curl module!\n");
        if (!function_exists('preg_match')) die("We need pcre module!\n");
        if (!function_exists('mcrypt_cbc')) die("We need mcrypt module!\n");
        if (!function_exists('bcmod')) die("We need bcmath module!\n");

        // generate GUID
        $this->machine_guid = '{'.$this->get_UUID().'}';

/*
    http://msnpiki.msnfanatic.com/index.php/MSNC:Client_Capabilities

    for official WLM 2009 14.0.8117.416
    the client capability is 2789003308 (0xA63CD02C)
    0x04(CapabilityRendersGif): Your client can send/receive Ink (GIF format)
    0x08(CapabilityRendersIsf): Your client can send/recieve Ink (ISF format)
    0x20(CapabilitySupportsChunking): This value is being used with Multi-Packet Messaging.
    0x1000(CapabilityHasSpace): This means you have a MSN Space.
    0x4000(CapabilitySupportsDirectIM): This means you support 'DirectIM' (creating direct connections for conversations rather than using the traditional switchboard)
    0x8000(CapabilitySupportsWinks): This means you support Winks receiving (If not set the official Client will warn with 'contact has an older client and is not capable of receiving Winks')
    0x40000(CapabilitySupportsVoiceIM): This means you support Voice Clips receiving
    0x80000(CapabilitySupportsSChannel): This means you support Secure Channel Communications
    0x100000(CapabilitySupportsSipInvite): Supports SIP Invitations
    0x200000(CapabilitySupportsTunneledSip): Supports Tunneled SIP
    0x2000000(CapabilityP2PSupportsTurn): Supports P2P TURN
    0x4000000(CapabilityP2PBootstrapViaUUN): Supports P2P Bootstrap via UUN
    0xA0000000(CapabilityMsgrVersion10): Supports MSNC10 (WL Msgr 14.0)

    the extended capability is 48 (0x30)
    0x10(CapabilityRTCVideoEnabled): RTC Video enabled
    0x20(CapabilityP2PV2): Supports P2PV2

    we just use following:
    0x04(CapabilityRendersGif): Your client can send/receive Ink (GIF format)
    0x08(CapabilityRendersIsf): Your client can send/recieve Ink (ISF format)
    0x8000(CapabilitySupportsWinks): This means you support Winks receiving (If not set the official Client will warn with 'contact has an older client and is not capable of receiving Winks')
    0xA0000000(CapabilityMsgrVersion10): Supports MSNC10 (WL Msgr 14.0)
    => 0xA000800C

*/
        $this->clientcapab = $client_capab.':'.$extended_capab;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            $this->windows = true;
        else
            $this->windows = false;
        return;
    }

    function get_UUID()
    {
        $workid = strtolower(md5(uniqid(rand(), true)));

        $byte = hexdec(substr($workid, 12, 2));
        $byte = $byte & hexdec('0f');
        $byte = $byte | hexdec('40');
        $workid = substr_replace($workid, strtolower(dechex($byte)), 12, 2);

        $byte = hexdec(substr($workid, 16, 2));
        $byte = $byte & hexdec('3f');
        $byte = $byte | hexdec('80');
        $workid = substr_replace($workid, strtolower(dechex($byte)), 16, 2);

        return substr($workid, 0, 8).'-'.substr($workid, 8, 4).'-'.substr($workid, 12, 4).'-'.substr($workid, 16, 4).'-'.substr($workid, 20, 12);
    }

    function get_passport_ticket($url = '')
    {
        $user = $this->user;
        $password = htmlspecialchars($this->password);

        if ($url === '')
            $passport_url = $this->passport_url;
        else
            $passport_url = $url;

        $XML = '<?xml version="1.0" encoding="UTF-8"?>
<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"
          xmlns:wsse="http://schemas.xmlsoap.org/ws/2003/06/secext"
          xmlns:saml="urn:oasis:names:tc:SAML:1.0:assertion"
          xmlns:wsp="http://schemas.xmlsoap.org/ws/2002/12/policy"
          xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
          xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/03/addressing"
          xmlns:wssc="http://schemas.xmlsoap.org/ws/2004/04/sc"
          xmlns:wst="http://schemas.xmlsoap.org/ws/2004/04/trust">
<Header>
  <ps:AuthInfo xmlns:ps="http://schemas.microsoft.com/Passport/SoapServices/PPCRL" Id="PPAuthInfo">
    <ps:HostingApp>{7108E71A-9926-4FCB-BCC9-9A9D3F32E423}</ps:HostingApp>
    <ps:BinaryVersion>4</ps:BinaryVersion>
    <ps:UIVersion>1</ps:UIVersion>
    <ps:Cookies></ps:Cookies>
    <ps:RequestParams>AQAAAAIAAABsYwQAAAAxMDMz</ps:RequestParams>
  </ps:AuthInfo>
  <wsse:Security>
    <wsse:UsernameToken Id="user">
      <wsse:Username>'.$user.'</wsse:Username>
      <wsse:Password>'.$password.'</wsse:Password>
    </wsse:UsernameToken>
  </wsse:Security>
</Header>
<Body>
  <ps:RequestMultipleSecurityTokens xmlns:ps="http://schemas.microsoft.com/Passport/SoapServices/PPCRL" Id="RSTS">
    <wst:RequestSecurityToken Id="RST0">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>http://Passport.NET/tb</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST1">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>messengerclear.live.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="'.$this->passport_policy.'"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST2">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>messenger.msn.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="?id=507"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST3">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>contacts.msn.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST4">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>messengersecure.live.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI_SSL"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST5">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>sup.live.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST6">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>storage.msn.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
  </ps:RequestMultipleSecurityTokens>
</Body>
</Envelope>';

        $this->debug_message("*** URL: $passport_url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $passport_url);
        // we also need header to get new location
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return false;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $passport_url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->get_passport_ticket($redirect_url);
            }
            // sometimes, rediret to another URL
            //<faultcode>psf:Redirect</faultcode>
            //<psf:redirectUrl>https://msnia.login.live.com/pp450/RST.srf</psf:redirectUrl>
            //<faultstring>Authentication Failure</faultstring>
            if (strpos($data, '<faultcode>psf:Redirect</faultcode>') === false) {
                $this->debug_message("*** Can't get passport ticket! http code = $http_code");
                return false;
            }
            preg_match("#<psf\:redirectUrl>(.*)</psf\:redirectUrl>#", $data, $matches);
            if (count($matches) == 0) {
                $this->debug_message("*** redirect, but can't get redirect URL!");
                return false;
            }
            $redirect_url = $matches[1];
            if ($redirect_url == $passport_url) {
                $this->debug_message("*** redirect, but redirect to same URL!");
                return false;
            }
            $this->debug_message("*** redirect to $redirect_url");
            return $this->get_passport_ticket($redirect_url);
        }

        // sometimes, rediret to another URL, also return 200
        //<faultcode>psf:Redirect</faultcode>
        //<psf:redirectUrl>https://msnia.login.live.com/pp450/RST.srf</psf:redirectUrl>
        //<faultstring>Authentication Failure</faultstring>
        if (strpos($data, '<faultcode>psf:Redirect</faultcode>') !== false) {
            preg_match("#<psf\:redirectUrl>(.*)</psf\:redirectUrl>#", $data, $matches);
            if (count($matches) != 0) {
                $redirect_url = $matches[1];
                if ($redirect_url == $passport_url) {
                    $this->debug_message("*** redirect, but redirect to same URL!");
                    return false;
                }
                $this->debug_message("*** redirect to $redirect_url");
                return $this->get_passport_ticket($redirect_url);
            }
        }

        // no Redurect faultcode or URL
        // we should get the ticket here

        // we need ticket and secret code
        // RST1: messengerclear.live.com
        // <wsse:BinarySecurityToken Id="Compact1">t=tick&p=</wsse:BinarySecurityToken>
        // <wst:BinarySecret>binary secret</wst:BinarySecret>
        // RST2: messenger.msn.com
        // <wsse:BinarySecurityToken Id="PPToken2">t=tick</wsse:BinarySecurityToken>
        // RST3: contacts.msn.com
        // <wsse:BinarySecurityToken Id="Compact3">t=tick&p=</wsse:BinarySecurityToken>
        // RST4: messengersecure.live.com
        // <wsse:BinarySecurityToken Id="Compact4">t=tick&p=</wsse:BinarySecurityToken>
        // RST5: sup.live.com
        // <wsse:BinarySecurityToken Id="Compact5">t=tick&p=</wsse:BinarySecurityToken>
        // RST6: storage.msn.com
        // <wsse:BinarySecurityToken Id="Compact6">t=tick&p=</wsse:BinarySecurityToken>
        preg_match("#".
                   "<wsse\:BinarySecurityToken Id=\"Compact1\">(.*)</wsse\:BinarySecurityToken>(.*)".
                   "<wst\:BinarySecret>(.*)</wst\:BinarySecret>(.*)".
                   "<wsse\:BinarySecurityToken Id=\"PPToken2\">(.*)</wsse\:BinarySecurityToken>(.*)".
                   "<wsse\:BinarySecurityToken Id=\"Compact3\">(.*)</wsse\:BinarySecurityToken>(.*)".
                   "<wsse\:BinarySecurityToken Id=\"Compact4\">(.*)</wsse\:BinarySecurityToken>(.*)".
                   "<wsse\:BinarySecurityToken Id=\"Compact5\">(.*)</wsse\:BinarySecurityToken>(.*)".
                   "<wsse\:BinarySecurityToken Id=\"Compact6\">(.*)</wsse\:BinarySecurityToken>(.*)".
                   "#",
                   $data, $matches);

        // no ticket found!
        if (count($matches) == 0) {
            // Since 2011/2/15, the return value will be Compact2, not PPToken2

            // we need ticket and secret code
            // RST1: messengerclear.live.com
            // <wsse:BinarySecurityToken Id="Compact1">t=tick&p=</wsse:BinarySecurityToken>
            // <wst:BinarySecret>binary secret</wst:BinarySecret>
            // RST2: messenger.msn.com
            // <wsse:BinarySecurityToken Id="PPToken2">t=tick</wsse:BinarySecurityToken>
            // RST3: contacts.msn.com
            // <wsse:BinarySecurityToken Id="Compact3">t=tick&p=</wsse:BinarySecurityToken>
            // RST4: messengersecure.live.com
            // <wsse:BinarySecurityToken Id="Compact4">t=tick&p=</wsse:BinarySecurityToken>
            // RST5: sup.live.com
            // <wsse:BinarySecurityToken Id="Compact5">t=tick&p=</wsse:BinarySecurityToken>
            // RST6: storage.msn.com
            // <wsse:BinarySecurityToken Id="Compact6">t=tick&p=</wsse:BinarySecurityToken>
            preg_match("#".
                       "<wsse\:BinarySecurityToken Id=\"Compact1\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wst\:BinarySecret>(.*)</wst\:BinarySecret>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact2\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact3\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact4\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact5\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact6\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "#",
                       $data, $matches);
            // no ticket found!
            if (count($matches) == 0) {
                $this->debug_message("*** Can't get passport ticket!");
                return false;
            }
        }

        //$this->debug_message(var_export($matches, true));
        // matches[0]: all data
        // matches[1]: RST1 (messengerclear.live.com) ticket
        // matches[2]: ...
        // matches[3]: RST1 (messengerclear.live.com) binary secret
        // matches[4]: ...
        // matches[5]: RST2 (messenger.msn.com) ticket
        // matches[6]: ...
        // matches[7]: RST3 (contacts.msn.com) ticket
        // matches[8]: ...
        // matches[9]: RST4 (messengersecure.live.com) ticket
        // matches[10]: ...
        // matches[11]: RST5 (sup.live.com) ticket
        // matches[12]: ...
        // matches[13]: RST6 (storage.live.com) ticket
        // matches[14]: ...

        // so
        // ticket => $matches[1]
        // secret => $matches[3]
        // web_ticket => $matches[5]
        // contact_ticket => $matches[7]
        // oim_ticket => $matches[9]
        // sup_ticket => $matches[11]
        // storage_ticket => $matches[13]

        // yes, we get ticket
        $aTickets = array(
                    'ticket' => html_entity_decode($matches[1]),
                    'secret' => html_entity_decode($matches[3]),
                    'web_ticket' => html_entity_decode($matches[5]),
                    'contact_ticket' => html_entity_decode($matches[7]),
                    'oim_ticket' => html_entity_decode($matches[9]),
                    'sup_ticket' => html_entity_decode($matches[11]),
                    'storage_ticket' => html_entity_decode($matches[13])
                    );
        //$this->debug_message(var_export($aTickets, true));
        return $aTickets;
    }

    function addContact($email, $network, $display = '', $sendADL = false, $new_location = false)
    {
        if ($network != 1) return true;
        if ($new_location === false)
            $url = $this->addcontact_url;
        else
            $url = $new_location;
        // add contact for WLM
        $ticket = htmlspecialchars($this->contact_ticket);
        $displayName = htmlspecialchars($display);
        $user = $email;

        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>'.$this->application_id.'</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactSave</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <ABContactAdd xmlns="http://www.msn.com/webservices/AddressBook">
        <abId>00000000-0000-0000-0000-000000000000</abId>
        <contacts>
            <Contact xmlns="http://www.msn.com/webservices/AddressBook">
                <contactInfo>
                    <contactType>LivePending</contactType>
                    <passportName>'.$user.'</passportName>
                    <isMessengerUser>true</isMessengerUser>
                    <MessengerMemberInfo>
                        <DisplayName>'.$displayName.'</DisplayName>
                    </MessengerMemberInfo>
                </contactInfo>
            </Contact>
        </contacts>
        <options>
            <EnableAllowListManagement>true</EnableAllowListManagement>
        </options>
    </ABContactAdd>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
                        'SOAPAction: '.$this->addcontact_soap,
                        'Content-Type: text/xml; charset=utf-8',
                        'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
                    );

        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return false;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->addContact($email, $network, $display, $sendADL, $location);
            }
            preg_match('#<faultcode>(.*)</faultcode><faultstring>(.*)</faultstring>#', $data, $matches);
            if (count($matches) == 0) {
                $this->log_message("*** can't add contact (network: $network) $email");
                return false;
            }
            $faultcode = trim($matches[1]);
            $faultstring = trim($matches[2]);
            $this->log_message("*** can't add contact (network: $network) $email, error code: $faultcode, $faultstring");
            return false;
        }
        $this->log_message("*** add contact (network: $network) $email");
        if ($sendADL && !feof($this->fp)) {
            @list($u_name, $u_domain) = @explode('@', $email);
            foreach (array('1', '2') as $l) {
                $str = '<ml l="1"><d n="'.$u_domain.'"><c n="'.$u_name.'" l="'.$l.'" t="'.$network.'" /></d></ml>';
                $len = strlen($str);
                // NS: >>> ADL {id} {size}
                $this->writeln("ADL $this->id $len");
                $this->writedata($str);
            }
        }
        return true;
    }

    function delMemberFromList($memberID, $email, $network, $list, $new_location = false)
    {
        if ($network != 1 && $network != 32) return true;
        if ($memberID === false) return true;
        if ($new_location === false)
            $url = $this->delmember_url;
        else
            $url = $new_location;
        $user = $email;
        $ticket = htmlspecialchars($this->contact_ticket);
        if ($network == 1)
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>'.$this->application_id.'</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <DeleteMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="PassportMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Passport</Type>
                        <MembershipId>'.$memberID.'</MembershipId>
                        <State>Accepted</State>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </DeleteMember>
</soap:Body>
</soap:Envelope>';
        else
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>'.$this->application_id.'</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <DeleteMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="EmailMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Email</Type>
                        <MembershipId>'.$memberID.'</MembershipId>
                        <State>Accepted</State>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </DeleteMember>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
                        'SOAPAction: '.$this->delmember_soap,
                        'Content-Type: text/xml; charset=utf-8',
                        'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
                    );

        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return false;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->delMemberFromList($memberID, $email, $network, $list, $location);
            }
            preg_match('#<faultcode>(.*)</faultcode><faultstring>(.*)</faultstring>#', $data, $matches);
            if (count($matches) == 0) {
                $this->log_message("*** can't delete member (network: $network) $email ($memberID) to $list");
                return false;
            }
            $faultcode = trim($matches[1]);
            $faultstring = trim($matches[2]);
            if (strcasecmp($faultcode, 'soap:Client') || stripos($faultstring, 'Member does not exist') === false) {
                $this->log_message("*** can't delete member (network: $network) $email ($memberID) to $list, error code: $faultcode, $faultstring");
                return false;
            }
            $this->log_message("*** delete member (network: $network) $email ($memberID) from $list, not exist");
            return true;
        }
        $this->log_message("*** delete member (network: $network) $email ($memberID) from $list");
        return true;
    }

    function addMemberToList($email, $network, $list, $new_location = false)
    {
        if ($network != 1 && $network != 32) return true;
        if ($new_location === false)
            $url = $this->addmember_url;
        else
            $url = $new_location;
        $ticket = htmlspecialchars($this->contact_ticket);
        $user = $email;

        if ($network == 1)
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>'.$this->application_id.'</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <AddMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="PassportMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Passport</Type>
                        <State>Accepted</State>
                        <PassportName>'.$user.'</PassportName>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </AddMember>
</soap:Body>
</soap:Envelope>';
        else
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>'.$this->application_id.'</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <AddMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="EmailMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Email</Type>
                        <State>Accepted</State>
                        <Email>'.$user.'</Email>
                        <Annotations>
                            <Annotation>
                                <Name>MSN.IM.BuddyType</Name>
                                <Value>32:YAHOO</Value>
                            </Annotation>
                        </Annotations>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </AddMember>
</soap:Body>
</soap:Envelope>';
        $header_array = array(
                        'SOAPAction: '.$this->addmember_soap,
                        'Content-Type: text/xml; charset=utf-8',
                        'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
                    );

        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return false;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->addMemberToList($email, $network, $list, $location);
            }
            preg_match('#<faultcode>(.*)</faultcode><faultstring>(.*)</faultstring>#', $data, $matches);
            if (count($matches) == 0) {
                $this->log_message("*** can't add member (network: $network) $email to $list");
                return false;
            }
            $faultcode = trim($matches[1]);
            $faultstring = trim($matches[2]);
            if (strcasecmp($faultcode, 'soap:Client') || stripos($faultstring, 'Member already exists') === false) {
                $this->log_message("*** can't add member (network: $network) $email to $list, error code: $faultcode, $faultstring");
                return false;
            }
            $this->log_message("*** add member (network: $network) $email to $list, already exist!");
            return true;
        }
        $this->log_message("*** add member (network: $network) $email to $list");
        return true;
    }

    function getMembershipList($new_location = false)
    {
        if ($new_location === false)
            $url = $this->membership_url;
        else
            $url = $new_location;
        $ticket = htmlspecialchars($this->contact_ticket);
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>'.$this->application_id.'</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>Initial</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <FindMembership xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceFilter>
            <Types>
                <ServiceType>Messenger</ServiceType>
                <ServiceType>Invitation</ServiceType>
                <ServiceType>SocialNetwork</ServiceType>
                <ServiceType>Space</ServiceType>
                <ServiceType>Profile</ServiceType>
            </Types>
        </serviceFilter>
    </FindMembership>
</soap:Body>
</soap:Envelope>';
        $header_array = array(
                            'SOAPAction: '.$this->membership_soap,
                            'Content-Type: text/xml; charset=utf-8',
                            'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
                        );
        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return array();
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->getMembershipList($location);
            }
            // no new location, return empty
            return array();
        }
        $p = $data;
        $aMemberships = array();
        while (1) {
            //$this->debug_message("search p = $p");
            $start = strpos($p, '<Membership>');
            $end = strpos($p, '</Membership>');
            if ($start === false || $end === false || $start > $end) break;
            //$this->debug_message("start = $start, end = $end");
            $end += 13;
            $sMembership = substr($p, $start, $end - $start);
            $aMemberships[] = $sMembership;
            //$this->debug_message("add sMembership = $sMembership");
            $p = substr($p, $end);
        }
        //$this->debug_message("aMemberships = ".var_export($aMemberships, true));

        $aContactList = array();
        foreach ($aMemberships as $sMembership) {
            //$this->debug_message("sMembership = $sMembership");
            if (isset($matches)) unset($matches);
            preg_match('#<MemberRole>(.*)</MemberRole>#', $sMembership, $matches);
            if (count($matches) == 0) continue;
            $sMemberRole = $matches[1];
            //$this->debug_message("MemberRole = $sMemberRole");
            if ($sMemberRole != 'Allow' && $sMemberRole != 'Reverse' && $sMemberRole != 'Pending') continue;
            $p = $sMembership;
            if (isset($aMembers)) unset($aMembers);
            $aMembers = array();
            while (1) {
                //$this->debug_message("search p = $p");
                $start = strpos($p, '<Member xsi:type="');
                $end = strpos($p, '</Member>');
                if ($start === false || $end === false || $start > $end) break;
                //$this->debug_message("start = $start, end = $end");
                $end += 9;
                $sMember = substr($p, $start, $end - $start);
                $aMembers[] = $sMember;
                //$this->debug_message("add sMember = $sMember");
                $p = substr($p, $end);
            }
            //$this->debug_message("aMembers = ".var_export($aMembers, true));
            foreach ($aMembers as $sMember) {
                //$this->debug_message("sMember = $sMember");
                if (isset($matches)) unset($matches);
                preg_match('#<Member xsi\:type="([^"]*)">#', $sMember, $matches);
                if (count($matches) == 0) continue;
                $sMemberType = $matches[1];
                //$this->debug_message("MemberType = $sMemberType");
                $network = -1;
                preg_match('#<MembershipId>(.*)</MembershipId>#', $sMember, $matches);
                if (count($matches) == 0) continue;
                $id = $matches[1];
                if ($sMemberType == 'PassportMember') {
                    if (strpos($sMember, '<Type>Passport</Type>') === false) continue;
                    $network = 1;
                    preg_match('#<PassportName>(.*)</PassportName>#', $sMember, $matches);
                }
                else if ($sMemberType == 'EmailMember') {
                    if (strpos($sMember, '<Type>Email</Type>') === false) continue;
                    // Value is 32: or 32:YAHOO
                    preg_match('#<Annotation><Name>MSN.IM.BuddyType</Name><Value>(.*):(.*)</Value></Annotation>#', $sMember, $matches);
                    if (count($matches) == 0) continue;
                    if ($matches[1] != 32) continue;
                    $network = 32;
                    preg_match('#<Email>(.*)</Email>#', $sMember, $matches);
                }
                if ($network == -1) continue;
                if (count($matches) > 0) {
                    $email = $matches[1];
                    @list($u_name, $u_domain) = @explode('@', $email);
                    if ($u_domain == NULL) continue;
                    $aContactList[$u_domain][$u_name][$network][$sMemberRole] = $id;
                    $this->log_message("*** add new contact (network: $network, status: $sMemberRole): $u_name@$u_domain ($id)");
                }
            }
        }
        return $aContactList;
    }

    function connect($user, $password, $redirect_server = '', $redirect_port = 1863)
    {
        $this->id = 1;
        $this->sbid = 1;
        if ($redirect_server === '') {
            $this->fp = @fsockopen($this->server, $this->port, $errno, $errstr, 5);
            if (!$this->fp) {
                $this->error = "Can't connect to $this->server:$this->port, error => $errno, $errstr";
                return false;
            }
        }
        else {
            $this->fp = @fsockopen($redirect_server, $redirect_port, $errno, $errstr, 5);
            if (!$this->fp) {
                $this->error = "Can't connect to $redirect_server:$redirect_port, error => $errno, $errstr";
                return false;
            }
        }

        stream_set_timeout($this->fp, $this->stream_timeout);
        $this->authed = false;
        // NS: >>> VER {id} MSNP18 CVR0
        $this->writeln("VER $this->id $this->protocol CVR0");

        $start_tm = time();
        while (!feof($this->fp)) {
            $data = $this->readln();
            // no data?
            if ($data === false) {
                if ($this->timeout > 0) {
                    $now_tm = time();
                    $used_time = ($now_tm >= $start_tm) ? $now_tm - $start_tm : $now_tm;
                    if ($used_time > $this->timeout) {
                        // logout now
                        // NS: >>> OUT
                        $this->writeln("OUT");
                        fclose($this->fp);
                        $this->error = 'Timeout, maybe protocol changed!';
                        $this->debug_message("*** $this->error");
                        return false;
                    }
                }
                continue;
            }
            $code = substr($data, 0, 3);
            $start_tm = time();
            switch ($code) {
                case 'VER':
                    // NS: <<< VER {id} MSNP18 CVR0
                    // NS: >>> CVR {id} 0x0409 winnt 5.1.3 i386 MSMSGS 14.0.8117.416 msmsgs {user}
                    $this->writeln("CVR $this->id $this->oslang $this->osname $this->osver $this->osplatform $this->clientname $this->clientver msmsgs $user");
                    break;

                case 'CVR':
                    // NS: <<< CVR {id} {ver_list} {download_serve} ....
                    // NS: >>> USR {id} SSO I {user}
                    $this->writeln("USR $this->id $this->login_method I $user");
                    break;

                case 'USR':
                    // already login for passport site, finish the login process now.
                    // NS: <<< USR {id} OK {user} {verify} 0
                    if ($this->authed) return true;

                    // max. 16 digits for password
                    if (strlen($password) > 16)
                        $password = substr($password, 0, 16);

                    $this->user = $user;
                    $this->password = $password;

                    // NS: <<< USR {id} SSO S {policy} {nonce}
                    @list(/* USR */, /* id */, /* SSO */, /* S */, $policy, $nonce,) = @explode(' ', $data);

                    $this->passport_policy = $policy;
                    $aTickets = $this->get_passport_ticket();
                    if (!$aTickets || !is_array($aTickets)) {
                        // logout now
                        // NS: >>> OUT
                        $this->writeln("OUT");
                        fclose($this->fp);
                        $this->error = 'Passport authenticated fail!';
                        $this->debug_message("*** $this->error");
                        return false;
                    }

                    $ticket = $aTickets['ticket'];
                    $secret = $aTickets['secret'];
                    $this->oim_ticket = $aTickets['oim_ticket'];
                    $this->contact_ticket = $aTickets['contact_ticket'];
                    $this->web_ticket = $aTickets['web_ticket'];
                    $this->sup_ticket = $aTickets['sup_ticket'];
                    $this->storage_ticket = $aTickets['storage_ticket'];

                    $login_code = $this->generateLoginBLOB($secret, $nonce);

                    // NS: >>> USR {id} SSO S {ticket} {login_code} {machine_guid}
                    $this->writeln("USR $this->id $this->login_method S $ticket $login_code $this->machine_guid");

                    $this->authed = true;
                    break;

                case 'XFR':
                    // main login server will redirect to anther NS after USR command
                    // NS: <<< XFR {id} NS {server} U D
                    @list(/* XFR */, /* id */, /* NS */, $server, /* ... */) = @explode(' ', $data);
                    @list($ip, $port) = @explode(':', $server);
                    // this connection will close after XFR
                    fclose($this->fp);

                    $this->fp = @fsockopen($ip, $port, $errno, $errstr, 5);
                    if (!$this->fp) {
                        $this->error = "Can't connect to $ip:$port, error => $errno, $errstr";
                        $this->debug_message("*** $this->error");
                        return false;
                    }
                    // reset trans id
                    $this->id = 1;

                    stream_set_timeout($this->fp, $this->stream_timeout);
                    // NS: >>> VER {id} MSNP18 CVR0
                    $this->writeln("VER $this->id $this->protocol CVR0");
                    break;

                case 'GCF':
                    // return some policy data after 'USR {id} SSO I {user}' command
                    // NS: <<< GCF 0 {size}
                    @list(/* GCF */, /* 0 */, $size,) = @explode(' ', $data);
                    // we don't need the data, just read it and drop
                    if (is_numeric($size) && $size > 0)
                        $this->readdata($size);
                    break;

                default:
                    // we'll quit if got any error
                    if (is_numeric($code)) {
                        // logout now
                        // NS: >>> OUT
                        $this->writeln("OUT");
                        fclose($this->fp);
                        $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                        $this->debug_message("*** $this->error");
                        return false;
                    }
                    // unknown response from server, just ignore it
                    break;
            }
        }
        // never goto here
    }

    function mhash_sha1($data, $key)
    {
        if (extension_loaded("mhash"))
            return mhash(MHASH_SHA1, $data, $key);

        // RFC 2104 HMAC implementation for php. Hacked by Lance Rushing
        $b = 64;
        if (strlen($key) > $b)
            $key = pack("H*", sha1($key));
        $key = str_pad($key, $b, chr(0x00));
        $ipad = str_pad("", $b, chr(0x36));
        $opad = str_pad("", $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        $sha1_value = sha1($k_opad . pack("H*", sha1($k_ipad . $data)));

        $hash_data = '';
        $str = join('',explode('\x', $sha1_value));
        $len = strlen($str);
        for ($i = 0; $i < $len; $i += 2)
            $hash_data .= chr(hexdec(substr($str, $i, 2)));
        return $hash_data;
    }

    function derive_key($key, $magic)
    {

        $hash1 = $this->mhash_sha1($magic, $key);
        $hash2 = $this->mhash_sha1($hash1.$magic, $key);
        $hash3 = $this->mhash_sha1($hash1, $key);
        $hash4 = $this->mhash_sha1($hash3.$magic, $key);
        return $hash2.substr($hash4, 0, 4);
    }

    function generateLoginBLOB($key, $challenge)
    {
        $key1 = base64_decode($key);
        $key2 = $this->derive_key($key1, 'WS-SecureConversationSESSION KEY HASH');
        $key3 = $this->derive_key($key1, 'WS-SecureConversationSESSION KEY ENCRYPTION');

        // get hash of challenge using key2
        $hash = $this->mhash_sha1($challenge, $key2);

        // get 8 bytes random data
        $iv = substr(base64_encode(rand(1000,9999).rand(1000,9999)), 2, 8);

        $cipher = mcrypt_cbc(MCRYPT_3DES, $key3, $challenge."\x08\x08\x08\x08\x08\x08\x08\x08", MCRYPT_ENCRYPT, $iv);

        $blob = pack('VVVVVVV', 28, 1, 0x6603, 0x8004, 8, 20, 72);
        $blob .= $iv;
        $blob .= $hash;
        $blob .= $cipher;

        return base64_encode($blob);
    }

    function getOIM_maildata($new_location = false)
    {
        preg_match('#t=(.*)&p=(.*)#', $this->web_ticket, $matches);
        if (count($matches) == 0) {
            $this->debug_message('*** no web ticket?');
            return false;
        }
        $t = htmlspecialchars($matches[1]);
        $p = htmlspecialchars($matches[2]);
        if ($new_location === false)
            $url = $this->oim_maildata_url;
        else
            $url = $new_location;
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <PassportCookie xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <t>'.$t.'</t>
    <p>'.$p.'</p>
  </PassportCookie>
</soap:Header>
<soap:Body>
  <GetMetadata xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi" />
</soap:Body>
</soap:Envelope>';

        $header_array = array(
                                'SOAPAction: '.$this->oim_maildata_soap,
                                'Content-Type: text/xml; charset=utf-8',
                                'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.$this->clientver.')'
                            );

        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return false;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->getOIM_maildata($location);
            }
            // no new location, return false
            $this->debug_message("*** Can't get OIM maildata! http code: $http_code");
            return false;
        }

        // <GetMetadataResponse xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">See #XML_Data</GetMetadataResponse>
        preg_match('#<GetMetadataResponse([^>]*)>(.*)</GetMetadataResponse>#', $data, $matches);
        if (count($matches) == 0) {
            $this->debug_message("*** Can't get OIM maildata");
            return '';
        }
        return $matches[2];
    }

    function delOIM_message($t, $p, $msgid, $new_location = false)
    {
        if ($new_location === false)
            $url = $this->oim_del_url;
        else
            $url = $new_location;
        // delete OIM
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <PassportCookie xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <t>'.$t.'</t>
    <p>'.$p.'</p>
  </PassportCookie>
</soap:Header>
<soap:Body>
  <DeleteMessages xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <messageIds>
      <messageId>'.$msgid.'</messageId>
    </messageIds>
  </DeleteMessages>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
                                'SOAPAction: '.$this->oim_del_soap,
                                'Content-Type: text/xml; charset=utf-8',
                                'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.$this->clientver.')'
                            );

        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return $sMsg;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->delOIM_message($t, $p, $msgid, $location);
            }
            // no new location, return empty
            $this->debug_message("*** Can't delete OIM: $msgid, http code = $http_code");
            return false;
        }
        $this->debug_message("*** OIM ($msgid) deleted");
        return true;
    }

    function getOIM_message($msgid, $new_location = false)
    {
        preg_match('#t=(.*)&p=(.*)#', $this->web_ticket, $matches);
        if (count($matches) == 0) {
            $this->debug_message('*** no web ticket?');
            return false;
        }
        $t = htmlspecialchars($matches[1]);
        $p = htmlspecialchars($matches[2]);
        if ($new_location === false)
            $url = $this->oim_read_url;
        else
            $url = $new_location;

        // read OIM
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <PassportCookie xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <t>'.$t.'</t>
    <p>'.$p.'</p>
  </PassportCookie>
</soap:Header>
<soap:Body>
  <GetMessage xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <messageId>'.$msgid.'</messageId>
    <alsoMarkAsRead>false</alsoMarkAsRead>
  </GetMessage>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
                                'SOAPAction: '.$this->oim_read_soap,
                                'Content-Type: text/xml; charset=utf-8',
                                'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.$this->clientver.')'
                            );

        $this->debug_message("*** URL: $url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // disable follow location
        // might be bug in crul... it follow the location, but seems not work, we need to do it by ourself
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // also need header for Location:
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        if ($data === false) {
            $this->debug_message("*** curl_exec() failed: ".curl_errno($curl)." => ".curl_error($curl));
            curl_close($curl);
            return false;
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // try Location: first
            $location = '';
            $aLines = @explode("\n", $data);
            foreach ($aLines as $line) {
                $line = rtrim($line);
                if (strncasecmp($line, "Location:", 9) == 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            // check url again, avoid infinite loop...
            if ($location !== '' && strcasecmp($location, $url) != 0) {
                // we have new location, try it again...
                $this->debug_message("*** Following new location:\n$location");
                return $this->getOIM_message($msgid, $location);
            }
            // no new location, return false
            $this->debug_message("*** Can't get OIM: $msgid, http code = $http_code");
            return false;
        }

        // why can't use preg_match('#<GetMessageResult>(.*)</GetMessageResult>#', $data, $matches)?
        // multi-lines?
        $start = strpos($data, '<GetMessageResult>');
        $end = strpos($data, '</GetMessageResult>');
        if ($start === false || $end === false || $start > $end) {
            $this->debug_message("*** Can't get OIM: $msgid");
            return false;
        }
        $lines = substr($data, $start + 18, $end - $start);
        $aLines = @explode("\n", $lines);
        $header = true;
        $ignore = false;
        $sOIM = '';
        foreach ($aLines as $line) {
            $line = rtrim($line);
            if ($header) {
                if ($line === '') {
                    $header = false;
                    continue;
                }
                continue;
            }
            // stop at empty lines
            if ($line === '') break;
            $sOIM .= $line;
        }
        $sMsg = base64_decode($sOIM);
        $this->debug_message("*** we get OIM ($msgid): $sMsg");

        $this->delOIM_message($t, $p, $msgid);
        return $sMsg;
    }

    function doLoop($aParams)
    {
        if ($this->kill_me) return;

        $user = $aParams['user'];
        $password = $aParams['password'];
        $alias = isset($aParams['alias']) ? $aParams['alias'] : '';
        $psm = isset($aParams['psm']) ? $aParams['psm'] : '';
        $my_ubx_function = isset($aParams['ubx_function']) ? $aParams['ubx_function'] : false;
        $my_function = isset($aParams['msg_function']) ? $aParams['msg_function'] : false;
        $my_add_function = isset($aParams['add_user_function']) ? $aParams['add_user_function'] : false;
        $my_rem_function = isset($aParams['remove_user_function']) ? $aParams['remove_user_function'] : false;
        $use_ping = isset($aParams['use_ping']) ? $aParams['use_ping'] : false;
        $retry_wait = isset($aParams['retry_wait']) ? $aParams['retry_wait'] : 30;
        $backup_file = isset($aParams['backup_file']) ? $aParams['backup_file'] : true;
        $update_pending = isset($aParams['update_pending']) ? $aParams['update_pending'] : true;
        $this->send_typing = isset($aParams['send_typing']) ? $aParams['send_typing'] : false;

        $this->log_message("*** startup ***");
        $process_file = false;
        $sent = false;
        $online = false;
        $aADL = array();
        if (is_int($use_ping) && $use_ping > 0)
            $ping_wait = $use_ping;
        else
            $ping_wait = 50;
        $aContactList = array();
        $first = true;
        while (1) {
            if ($this->kill_me) {
                if (is_resource($this->fp) && !feof($this->fp)) {
                    // logout now
                    // NS: >>> OUT
                    $this->writeln("OUT");
                    fclose($this->fp);
                    $this->fp = false;
                    $this->log_message("*** logout now!");
                }
                $this->log_message("*** Okay, kill me now!");
                break;
            }
            if (!is_resource($this->fp) || feof($this->fp)) {
                if ($first)
                    $first = false;
                else {
                    $this->log_message("*** wait for $retry_wait seconds");
                    sleep($retry_wait);
                }
                if ($this->kill_me) continue;
                $process_file = false;
                $sent = false;
                $online = false;
                $aADL = array();
                $aContactList = array();
                $this->log_message("*** try to connect to MSN network");
                if (!$this->connect($user, $password)) {
                    $this->log_message("!!! Can't connect to server: $this->error");
                    continue;
                }
                // for old version, after 'USR {id} OK {user} {verify} 0' response, the server will send SBS and profile to us
                // protocol changed, we won't receive 'SBS' since 2009/07/21, so it won't contonuse for old version
                // so we just retrieve the member after USR command, then ignore SBS...
                $aContactList = $this->getMembershipList();
                if ($update_pending) {
                    if (is_array($aContactList)) {
                        $pending = 'Pending';
                        foreach ($aContactList as $u_domain => $aUserList) {
                            foreach ($aUserList as $u_name => $aNetworks) {
                                foreach ($aNetworks as $network => $aData) {
                                    if (isset($aData[$pending])) {
                                        // pending list
                                        $cnt = 0;
                                        foreach (array('Allow', 'Reverse') as $list) {
                                            if (isset($aData[$list]))
                                                $cnt++;
                                            else {
                                                if ($this->addMemberToList($u_name.'@'.$u_domain, $network, $list)) {
                                                    $aContactList[$u_domain][$u_name][$network][$list] = false;
                                                    $cnt++;
                                                }
                                            }
                                        }
                                        if ($cnt >= 2) {
                                            $id = $aData[$pending];
                                            // we can delete it from pending now
                                            if ($this->delMemberFromList($id, $u_name.'@'.$u_domain, $network, $pending))
                                                unset($aContactList[$u_domain][$u_name][$network][$pending]);
                                        }
                                    }
                                    else {
                                        // sync list
                                        foreach (array('Allow', 'Reverse') as $list) {
                                            if (!isset($aData[$list])) {
                                                if ($this->addMemberToList($u_name.'@'.$u_domain, $network, $list))
                                                    $aContactList[$u_domain][$u_name][$network][$list] = false;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $n = 0;
                $sList = '';
                $len = 0;
                if (is_array($aContactList)) {
                    foreach ($aContactList as $u_domain => $aUserList) {
                        $str = '<d n="'.$u_domain.'">';
                        $len += strlen($str);
                        if ($len > 7400) {
                            $aADL[$n] = '<ml l="1">'.$sList.'</ml>';
                            $n++;
                            $sList = '';
                            $len = strlen($str);
                        }
                        $sList .= $str;
                        foreach ($aUserList as $u_name => $aNetworks) {
                            foreach ($aNetworks as $network => $status) {
                                $str = '<c n="'.$u_name.'" l="3" t="'.$network.'" />';
                                $len += strlen($str);
                                // max: 7500, but <ml l="1"></d></ml> is 19,
                                // so we use 7475
                                if ($len > 7475) {
                                    $sList .= '</d>';
                                    $aADL[$n] = '<ml l="1">'.$sList.'</ml>';
                                    $n++;
                                    $sList = '<d n="'.$u_domain.'">'.$str;
                                    $len = strlen($sList);
                                }
                                else
                                    $sList .= $str;
                            }
                        }
                        $sList .= '</d>';
                    }
                }
                $aADL[$n] = '<ml l="1">'.$sList.'</ml>';
                foreach ($aADL as $str) {
                    $len = strlen($str);
                    // NS: >>> ADL {id} {size}
                    $this->writeln("ADL $this->id $len");
                    $this->writedata($str);
                }
                // NS: >>> UUX {id} length
                $str = '<EndpointData><Capabilities>'.$this->clientcapab.'</Capabilities></EndpointData>';
                $len = strlen($str);
                $this->writeln("UUX $this->id $len");
                $this->writedata($str);
                // NS: >>> UUX {id} length
                $str = '<PrivateEndpointData><EpName>PHPMSNCLASS</EpName><Idle>false</Idle><ClientType>1</ClientType><State>NLN</State></PrivateEndpointData>';
                $len = strlen($str);
                $this->writeln("UUX $this->id $len");
                $this->writedata($str);
                // NS: >>> BLP {id} BL
                $this->writeln("BLP $this->id BL");
                // NS: >>> UUX {id} length
                $str = '<Data><PSM>'.htmlspecialchars($psm).'</PSM><CurrentMedia></CurrentMedia><MachineGuid>'.$this->machine_guid.'</MachineGuid></Data>';
                $len = strlen($str);
                $this->writeln("UUX $this->id $len");
                $this->writedata($str);
                // NS: >>> PRP {id} MFN name
                if ($alias == '') $alias = $user;
                $aliasname = rawurlencode($alias);
                $this->writeln("PRP $this->id MFN $aliasname");
                // NS: >>> CHG {id} {status} {clientcapab} {msnobj}
                $this->writeln("CHG $this->id NLN ".$this->clientcapab);
                $online = true;

                $this->log_message("*** connected, wait for command");
                $start_tm = time();
                $ping_tm = time();
                stream_set_timeout($this->fp, $this->stream_timeout);
            }
            $data = $this->readln();
            if ($data === false) {
                if ($this->kill_me) continue;
                if ($process_file !== false && $sent !== true) {
                    // some error for sending message, timeout?
                    if ($this->timeout > 0) {
                        $now_tm = time();
                        $used_time = ($now_tm >= $start_tm) ? $now_tm - $start_tm : $now_tm;
                        // not yet, wait again
                        if ($used_time <= $this->timeout)
                            continue;
                    }
                    $this->log_message("*** stop for message in $process_file");
                    $aMSNUsers = array();
                    $aOfflineUsers = array();
                    $aOtherUsers = array();
                    $nMSNUsers = 0;
                    $process_file = false;
                    continue;
                }
                // check here, do we have any message need to send?
                $aFiles = glob(dirname($_SERVER['argv'][0]).DIRECTORY_SEPARATOR.'spool'.DIRECTORY_SEPARATOR.'*.msn');
                if (!is_array($aFiles)) continue;
                clearstatcache();
                foreach ($aFiles as $filename) {
                    if (fileperms($filename) != (0x8000 | 00666)) continue;
                    $fp = fopen($filename, 'rt');
                    if (!$fp) continue;
                    $aTo = array();
                    $sMessage = '';
                    $buf = trim(fgets($fp));
                    if (substr($buf, 0, 3) == 'TO:') {
                        $to_str = trim(substr($buf, 3));
                        $aTo = @explode(',', $to_str);
                        $add_crlf = false;
                        while (!feof($fp)) {
                            $buf = rtrim(fgets($fp));
                            if ($sMessage !== '' || $add_crlf)
                                $sMessage .= "\n";
                            $add_crlf = true;
                            $sMessage .= $buf;
                        }
                    }
                    fclose($fp);
                    if (!is_array($aTo) || count($aTo) == 0 || $sMessage === '' || $to_str === '') {
                        $this->log_message("!!! message format error? delete $filename");
                        if ($backup_file) {
                            $backup_dir = dirname($_SERVER['argv'][0]).'/backup';
                            if (!file_exists($backup_dir))
                                @mkdir($backup_dir);
                            $backup_name = $backup_dir.'/'.strftime('%Y%m%d%H%M%S').'_'.$this->getpid().'_'.basename($filename);
                            if (@rename($filename, $backup_name))
                                $this->log_message("*** move file to $backup_name");
                        }
                        @unlink($filename);
                        continue;
                    }
                    // assign process_file
                    $process_file = $filename;
                    break;
                }

                if ($process_file === false) {
                    if ($online && $use_ping) {
                        $now = time();
                        if ($now < $ping_tm)
                            $len =  $now;
                        else
                            $len = $now - $ping_tm;
                        if ($len > $ping_wait) {
                            // NS: >>> PNG
                            $this->writeln("PNG");
                            $ping_tm = time();
                        }
                    }
                    continue;
                }
                $sent = false;

                $this->log_message("*** try to send message from $process_file");
                $this->log_message("*** TO: $to_str");
                $this->log_message("*** MSG: $sMessage");

                $aMSNUsers = array();
                $aOfflineUsers = array();
                $aOtherUsers = array();
                $nMSNUsers = 0;
                foreach ($aTo as $sUser) {
                    @list($u_user, $u_domain, $u_network) = @explode('@', $sUser);
                    if ($u_network === '' || $u_network == NULL)
                        $u_network = 1;
                    $to_email = trim($u_user.'@'.$u_domain);
                    if ($u_network == 1)
                        $aMSNUsers[$nMSNUsers++] = $to_email;
                    else
                        $aOtherUsers[$u_network][] = $to_email;
                }
                if ($nMSNUsers == 0) {
                    // no MSN account, only others
                    foreach ($aOtherUsers as $network => $aNetUsers) {
                        $aMessage = $this->getMessage($sMessage, $network);
                        foreach ($aNetUsers as $to) {
                            foreach ($aMessage as $message) {
                                $len = strlen($message);
                                $this->writeln("UUM $this->id $to $network 1 $len");
                                $this->writedata($message);
                            }
                            $this->log_message("*** sent to $to (network: $network):\n$sMessage");
                        }
                    }
                    $this->log_message("*** already send message from $process_file");
                    if ($backup_file) {
                        $backup_dir = dirname($_SERVER['argv'][0]).'/backup';
                        if (!file_exists($backup_dir))
                            @mkdir($backup_dir);
                        $backup_name = $backup_dir.'/'.strftime('%Y%m%d%H%M%S').'_'.$this->getpid().'_'.basename($process_file);
                        if (@rename($process_file, $backup_name))
                            $this->log_message("*** move file to $backup_name");
                    }
                    @unlink($process_file);
                    $process_file = false;
                    $sent = true;
                    continue;
                }

                $start_tm = time();
                $nCurrentUser = 0;

                // okay, try to ask a switchboard (SB) for sending message
                // NS: >>> XFR {id} SB
                $this->writeln("XFR $this->id SB");
                $this->debug_message("*** process ".$aMSNUsers[$nCurrentUser]." ($nCurrentUser/$nMSNUsers)");
                continue;
            }
            $code = substr($data, 0, 3);
            $start_tm = time();
            //$this->debug_message("got code => $code from <<< $data");

            switch ($code) {
                case 'SBS':
                    // after 'USR {id} OK {user} {verify} 0' response, the server will send SBS and profile to us
                    // NS: <<< SBS 0 null
                    // ignore it now, because we already process it after USR response
                    break;

                case 'RFS':
                    // FIXME:
                    // NS: <<< RFS ???
                    // refresh ADL, so we re-send it again
                    if (is_array($aADL)) {
                        foreach ($aADL as $str) {
                            $len = strlen($str);
                            // NS: >>> ADL {id} {size}
                            $this->writeln("ADL $this->id $len");
                            $this->writedata($str);
                        }
                    }
                    break;

                case 'LST':
                    // NS: <<< LST {email} {alias} 11 0
                    @list(/* LST */, $email, /* alias */, ) = @explode(' ', $data);
                    @list($u_name, $u_domain) = @explode('@', $email);
                    if (!isset($aContactList[$u_domain][$u_name][1])) {
                        $aContactList[$u_domain][$u_name][1]['Allow'] = 'Allow';
                        $this->log_message("*** add to our contact list: $u_name@$u_domain");
                    }
                    break;

                case 'ADD':
                    // randomly, we get ADD command, someome add us to their contact list for MSNP9
                    // NS: <<< ADD 0 {list} {0} {email} {alias}
                    @list(/* ADD */, /* 0 */, $u_list, /* 0 */, $u_email, /* alias */) = @explode(' ', $data);
                    @list($u_name, $u_domain) = @explode('@', $u_email);
                    if (isset($aContactList[$u_domain][$u_name][1]['Allow']))
                        $this->log_message("*** someone add us to their list (but already in our list): $u_name@$u_domain");
                    else {
                        $aContactList[$u_domain][$u_name][1]['Allow'] = 'Allow';
                        $this->log_message("*** someone add us to their list: $u_name@$u_domain");
                    }
                    if ($my_add_function && function_exists($my_add_function) && is_callable($my_add_function))
                        $my_add_function($u_email);
                    break;

                case 'REM':
                    // randomly, we get REM command, someome remove us from their contact list for MSNP9
                    // NS: <<< REM 0 {list} {0} {email}
                    @list(/* REM */, /* 0 */, $u_list, /* 0 */, $u_email,) = @explode(' ', $data);
                    @list($u_name, $u_domain) = @explode('@', $u_email);
                    if (isset($aContactList[$u_domain][$u_name][1])) {
                        unset($aContactList[$u_domain][$u_name][1]);
                        $this->log_message("*** someone remove us from their list: $u_name@$u_domain");
                    }
                    else
                        $this->log_message("*** someone remove us from their list (but not in our list): $u_name@$u_domain");
                    if ($my_rem_function && function_exists($my_rem_function) && is_callable($my_rem_function))
                        $my_rem_function($u_email);
                    break;

                case 'ADL':
                    // randomly, we get ADL command, someome add us to their contact list for MSNP15
                    // NS: <<< ADL 0 {size}
                    @list(/* ADL */, /* 0 */, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0) {
                        $data = $this->readdata($size);
                        preg_match('#<ml><d n="([^"]+)"><c n="([^"]+)"(.*) t="(\d*)"(.*) /></d></ml>#', $data, $matches);
                        if (is_array($matches) && count($matches) > 0) {
                            $u_domain = $matches[1];
                            $u_name = $matches[2];
                            $network = $matches[4];
                            if (isset($aContactList[$u_domain][$u_name][$network]))
                                $this->log_message("*** someone (network: $network) add us to their list (but already in our list): $u_name@$u_domain");
                            else {
                                $re_login = false;
                                $cnt = 0;
                                foreach (array('Allow', 'Reverse') as $list) {
                                    if (!$this->addMemberToList($u_name.'@'.$u_domain, $network, $list)) {
                                        if ($re_login) {
                                            $this->log_message("*** can't add $u_name@$u_domain (network: $network) to $list");
                                            continue;
                                        }
                                        $aTickets = $this->get_passport_ticket();
                                        if (!$aTickets || !is_array($aTickets)) {
                                            // failed to login? ignore it
                                            $this->log_message("*** can't re-login, something wrong here");
                                            $this->log_message("*** can't add $u_name@$u_domain (network: $network) to $list");
                                            continue;
                                        }
                                        $re_login = true;
                                        $this->oim_ticket = $aTickets['oim_ticket'];
                                        $this->contact_ticket = $aTickets['contact_ticket'];
                                        $this->web_ticket = $aTickets['web_ticket'];
                                        $this->sup_ticket = $aTickets['sup_ticket'];
                                        $this->storage_ticket = $aTickets['storage_ticket'];
                                        $this->log_message("**** get new ticket, try it again");
                                        if (!$this->addMemberToList($u_name.'@'.$u_domain, $network, $list)) {
                                            $this->log_message("*** can't add $u_name@$u_domain (network: $network) to $list");
                                            continue;
                                        }
                                    }
                                    $aContactList[$u_domain][$u_name][$network][$list] = false;
                                    $cnt++;
                                }
                                $this->log_message("*** someone (network: $network) add us to their list: $u_name@$u_domain");
                            }
                            $str = '<ml l="1"><d n="'.$u_domain.'"><c n="'.$u_name.'" l="3" t="'.$network.'" /></d></ml>';
                            $len = strlen($str);
                            // NS: >>> ADL {id} {size}
                            $this->writeln("ADL $this->id $len");
                            $this->writedata($str);
                            if ($my_add_function && function_exists($my_add_function) && is_callable($my_add_function))
                                $my_add_function($u_name.'@'.$u_domain, $network);
                        }
                        else {
                            $this->log_message("*** someone add us to their list: $data");
                        }
                    }
                    break;

                case 'RML':
                    // randomly, we get RML command, someome remove us to their contact list for MSNP15
                    // NS: <<< RML 0 {size}
                    @list(/* RML */, /* 0 */, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0) {
                        $data = $this->readdata($size);
                        preg_match('#<ml><d n="([^"]+)"><c n="([^"]+)"(.*) t="(\d*)"(.*) /></d></ml>#', $data, $matches);
                        if (is_array($matches) && count($matches) > 0) {
                            $u_domain = $matches[1];
                            $u_name = $matches[2];
                            $network = $matches[4];
                            if (isset($aContactList[$u_domain][$u_name][$network])) {
                                $aData = $aContactList[$u_domain][$u_name][$network];
                                foreach ($aData as $list => $id)
                                    $this->delMemberFromList($id, $u_name.'@'.$u_domain, $network, $list);
                                unset($aContactList[$u_domain][$u_name][$network]);
                                $this->log_message("*** someone (network: $network) remove us from their list: $u_name@$u_domain");
                            }
                            else
                                $this->log_message("*** someone (network: $network) remove us from their list (but not in our list): $u_name@$u_domain");
                            if ($my_rem_function && function_exists($my_rem_function) && is_callable($my_rem_function))
                                $my_rem_function($u_name.'@'.$u_domain, $network);
                        }
                        else {
                            $this->log_message("*** someone remove us from their list: $data");
                        }
                    }
                    break;

                case 'MSG':
                    // randomly, we get MSG notification from server
                    // NS: <<< MSG Hotmail Hotmail {size}
                    @list(/* MSG */, /* Hotmail */, /* Hotmail */, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0) {
                        $data = $this->readdata($size);
                        $aLines = @explode("\n", $data);
                        $header = true;
                        $ignore = false;
                        $maildata = '';
                        foreach ($aLines as $line) {
                            $line = rtrim($line);
                            if ($header) {
                                if ($line === '') {
                                    $header = false;
                                    continue;
                                }
                                if (strncasecmp($line, 'Content-Type:', 13) == 0) {
                                    if (strpos($line, 'text/x-msmsgsinitialmdatanotification') === false &&
                                        strpos($line, 'text/x-msmsgsoimnotification') === false) {
                                        // we just need text/x-msmsgsinitialmdatanotification
                                        // or text/x-msmsgsoimnotification
                                        $ignore = true;
                                        break;
                                    }
                                }
                                continue;
                            }
                            if (strncasecmp($line, 'Mail-Data:', 10) == 0) {
                                $maildata = trim(substr($line, 10));
                                break;
                            }
                        }
                        if ($ignore) {
                            $this->log_message("*** ingnore MSG for: $line");
                            break;
                        }
                        if ($maildata == '') {
                            $this->log_message("*** ingnore MSG not for OIM");
                            break;
                        }
                        $re_login = false;
                        if (strcasecmp($maildata, 'too-large') == 0) {
                            $this->log_message("*** large mail-data, need to get the data via SOAP");
                            $maildata = $this->getOIM_maildata();
                            if ($maildata === false) {
                                $this->log_message("*** can't get mail-data via SOAP");
                                // maybe we need to re-login again
                                $aTickets = $this->get_passport_ticket();
                                if (!$aTickets || !is_array($aTickets)) {
                                    // failed to login? ignore it
                                    $this->log_message("*** can't re-login, something wrong here, ignore this OIM");
                                    break;
                                }
                                $re_login = true;
                                $this->oim_ticket = $aTickets['oim_ticket'];
                                $this->contact_ticket = $aTickets['contact_ticket'];
                                $this->web_ticket = $aTickets['web_ticket'];
                                $this->sup_ticket = $aTickets['sup_ticket'];
                                $this->storage_ticket = $aTickets['storage_ticket'];
                                $this->log_message("**** get new ticket, try it again");
                                $maildata = $this->getOIM_maildata();
                                if ($maildata === false) {
                                    $this->log_message("*** can't get mail-data via SOAP, and we already re-login again, so ignore this OIM");
                                    break;
                                }
                            }
                        }
                        // could be a lots of <M>...</M>, so we can't use preg_match here
                        $p = $maildata;
                        $aOIMs = array();
                        while (1) {
                            $start = strpos($p, '<M>');
                            $end = strpos($p, '</M>');
                            if ($start === false || $end === false || $start > $end) break;
                            $end += 4;
                            $sOIM = substr($p, $start, $end - $start);
                            $aOIMs[] = $sOIM;
                            $p = substr($p, $end);
                        }
                        if (count($aOIMs) == 0) {
                            $this->log_message("*** ingnore empty OIM");
                            break;
                        }
                        foreach ($aOIMs as $maildata) {
                            // T: 11 for MSN, 13 for Yahoo
                            // S: 6 for MSN, 7 for Yahoo
                            // RT: the datetime received by server
                            // RS: already read or not
                            // SZ: size of message
                            // E: sender
                            // I: msgid
                            // F: always 00000000-0000-0000-0000-000000000009
                            // N: sender alias
                            preg_match('#<T>(.*)</T>#', $maildata, $matches);
                            if (count($matches) == 0) {
                                $this->log_message("*** ingnore OIM maildata without <T>type</T>");
                                continue;
                            }
                            $oim_type = $matches[1];
                            if ($oim_type == 13)
                                $network = 32;
                            else
                                $network = 1;
                            preg_match('#<E>(.*)</E>#', $maildata, $matches);
                            if (count($matches) == 0) {
                                $this->log_message("*** ingnore OIM maildata without <E>sender</E>");
                                continue;
                            }
                            $oim_sender = $matches[1];
                            preg_match('#<I>(.*)</I>#', $maildata, $matches);
                            if (count($matches) == 0) {
                                $this->log_message("*** ingnore OIM maildata without <I>msgid</I>");
                                continue;
                            }
                            $oim_msgid = $matches[1];
                            preg_match('#<SZ>(.*)</SZ>#', $maildata, $matches);
                            $oim_size = (count($matches) == 0) ? 0 : $matches[1];
                            preg_match('#<RT>(.*)</RT>#', $maildata, $matches);
                            $oim_time = (count($matches) == 0) ? 0 : $matches[1];
                            $this->log_message("*** You've OIM sent by $oim_sender, Time: $oim_time, MSGID: $oim_msgid, size: $oim_size");
                            $sMsg = $this->getOIM_message($oim_msgid);
                            if ($sMsg === false) {
                                $this->log_message("*** can't get OIM, msgid = $oim_msgid");
                                if ($re_login) {
                                    $this->log_message("*** can't get OIM via SOAP, and we already re-login again, so ignore this OIM");
                                    continue;
                                }
                                $aTickets = $this->get_passport_ticket();
                                if (!$aTickets || !is_array($aTickets)) {
                                    // failed to login? ignore it
                                    $this->log_message("*** can't re-login, something wrong here, ignore this OIM");
                                    continue;
                                }
                                /* re-assign PSM */
                                if ($alias == '') $alias = $user;
                                $aliasname = rawurlencode($alias);
                                $this->writeln("PRP $this->id MFN $aliasname");
                                // NS: >>> UUX {id} length
                                $str = '<Data><PSM>'.htmlspecialchars($psm).'</PSM><CurrentMedia></CurrentMedia><MachineGuid>'.$this->machine_guid.'</MachineGuid></Data>';
                                $len = strlen($str);
                                $this->writeln("UUX $this->id $len");
                                $this->writedata($str);
                                /* re-assign PSM */
                                $re_login = true;
                                $this->oim_ticket = $aTickets['oim_ticket'];
                                $this->contact_ticket = $aTickets['contact_ticket'];
                                $this->web_ticket = $aTickets['web_ticket'];
                                $this->sup_ticket = $aTickets['sup_ticket'];
                                $this->storage_ticket = $aTickets['storage_ticket'];
                                $this->log_message("**** get new ticket, try it again");
                                $sMsg = $this->getOIM_message($oim_msgid);
                                if ($sMsg === false) {
                                    $this->log_message("*** can't get OIM via SOAP, and we already re-login again, so ignore this OIM");
                                    continue;
                                }
                            }
                            $this->log_message("*** MSG (Offline) from $oim_sender (network: $network): $sMsg");
                            if ($my_function && function_exists($my_function) && is_callable($my_function)) {
                                $sMessage = $my_function($oim_sender, $sMsg, $network);
                                if ($sMessage !== '') {
                                    $now = strftime('%m/%d/%y %H:%M:%S');
                                    $fname = dirname($_SERVER['argv'][0]).DIRECTORY_SEPARATOR.'spool'.DIRECTORY_SEPARATOR.'msn_'.$this->getpid().'_'.md5('offline'.rand(1,1000).$now).'.msn';
                                    $fp = fopen($fname, 'wt');
                                    if ($fp) {
                                        fputs($fp, "TO: $oim_sender@$network\n");
                                        fputs($fp, $sMessage);
                                        fclose($fp);
                                        chmod($fname, 0666);
                                        $this->log_message("Response to $oim_sender (Offline, network: $network): $fname");
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'UBM':
                    // randomly, we get UBM, this is the message from other network, like Yahoo!
                    // NS: <<< UBM {from_email} $from_network $to_email $to_network $type {size}
                    @list(/* UBM */, $from_email, $from_network, $to_email, $to_network, $type, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0) {
                        $data = $this->readdata($size);
                        $aLines = @explode("\n", $data);
                        $header = true;
                        $ignore = false;
                        $sMsg = '';
                        foreach ($aLines as $line) {
                            $line = rtrim($line);
                            if ($header) {
                                if ($line === '') {
                                    $header = false;
                                    continue;
                                }
                                if (strncasecmp($line, 'TypingUser:', 11) == 0) {
                                    $ignore = true;
                                    break;
                                }
                                continue;
                            }
                            $aSubLines = @explode("\r", $line);
                            foreach ($aSubLines as $str) {
                                if ($sMsg !== '')
                                    $sMsg .= "\n";
                                $sMsg .= $str;
                            }
                        }
                        if ($ignore) {
                            $this->log_message("*** ingnore from $from_email: $line");
                            break;
                        }
                        $this->log_message("*** MSG from $from_email (network: $network): $sMsg");
                        if ($my_function && function_exists($my_function) && is_callable($my_function)) {
                            $reply_msg = $my_function($from_email, $sMsg, $network);
                            if ($reply_msg !== '') {
                                if ($this->send_typing) {
                                    $message = "MIME-Version: 1.0\r\nContent-Type: text/x-msmsgscontrol\r\nTypingUser: $user\r\n\r\n\r\n";
                                    $len = strlen($message);
                                    $this->writeln("UUM $this->id $from_email $network 2 $len");
                                    $this->writedata($message);
                                }
                                $aMessage = $this->getMessage($reply_msg, $network);
                                foreach ($aMessage as $message) {
                                    $len = strlen($message);
                                    $this->writeln("UUM $this->id $from_email $network 1 $len");
                                    $this->writedata($message);
                                }
                                $this->log_message("Response to $from_email (network: $network):\n$reply_msg");
                            }
                        }
                    }
                    break;

                case 'UBX':
                    // randomly, we get UBX notification from server
                    // NS: <<< UBX network:email {size}
                    @list(/* UBX */, $from_data, $size,) = @explode(' ', $data);
                    // we don't need the notification data, so just ignore it
                    if (is_numeric($size) && $size > 0)
                        $data = $this->readdata($size);
                    @list($from_network, $from_email) = @explode(':', $from_data);
                    if ($my_ubx_function && function_exists($my_ubx_function) && is_callable($my_ubx_function)) {
                        $sMessage = $my_ubx_function($from_email, $data, $from_network);
                        if ($sMessage !== '') {
                            $now = strftime('%m/%d/%y %H:%M:%S');
                            $fname = dirname($_SERVER['argv'][0]).DIRECTORY_SEPARATOR.'spool'.DIRECTORY_SEPARATOR.'msn_'.$this->getpid().'_'.md5('ubx'.rand(1,1000).$now).'.msn';
                            $fp = fopen($fname, 'wt');
                            if ($fp) {
                                fputs($fp, "TO: $from_email@$from_network\n");
                                fputs($fp, $sMessage);
                                fclose($fp);
                                chmod($fname, 0666);
                                $this->log_message("UBX Response to $from_email (network: $from_network): $fname");
                            }
                        }
                    }
                    break;

                case 'CHL':
                    // randomly, we'll get challenge from server
                    // NS: <<< CHL 0 {code}
                    @list(/* CHL */, /* 0 */, $chl_code,) = @explode(' ', $data);
                    $fingerprint = $this->getChallenge($chl_code);
                    // NS: >>> QRY {id} {product_id} 32
                    // NS: >>> fingerprint
                    $this->writeln("QRY $this->id $this->prod_id 32");
                    $this->writedata($fingerprint);
                    break;

                case 'CHG':
                    // NS: <<< CHG {id} {status} {code}
                    // ignore it
                    // change our status to online first
                    break;

                case 'XFR':
                    // sometimes, NS will redirect to another NS
                    // NS: <<< XFR {id} NS {server} U D
                    // for normal switchboard XFR
                    // NS: <<< XFR {id} SB {server} CKI {cki} U messenger.msn.com 0
                    @list(/* XFR */, /* {id} */, $server_type, $server, /* CKI */, $cki_code, /* ... */) = @explode(' ', $data);
                    @list($ip, $port) = @explode(':', $server);
                    if ($server_type == 'NS') {
                        // this connection will close after XFR
                        $this->writeln("OUT");
                        fclose($this->fp);
                        $this->fp = false;
                        // login again
                        if (!$this->connect($user, $password, $ip, $port)) {
                            $this->log_message("!!! Can't connect to server: $this->error");
                            continue;
                        }
                        $this->log_message("*** connected, wait for command");
                        $start_tm = time();
                        $ping_tm = time();
                        stream_set_timeout($this->fp, $this->stream_timeout);
                        continue;
                    }
                    $this->error = '';
                    if ($server_type != 'SB' || $nMSNUsers == 0) {
                        // maybe exit?
                        // this connection will close after XFR
                        $this->writeln("OUT");
                        fclose($this->fp);
                        $this->fp = false;
                        continue;
                    }

                    $bSBresult = $this->switchboard_control($ip, $port, $cki_code, $aMSNUsers[$nCurrentUser], $sMessage);
                    if ($bSBresult === false) {
                        // error for switchboard
                        $this->log_message("!!! error for sending message to ".$aMSNUsers[$nCurrentUser]);
                        $aOfflineUsers[] = $aMSNUsers[$nCurrentUser];
                    }

                    $nCurrentUser++;
                    if ($nCurrentUser < $nMSNUsers) {
                        // for next user
                        // okay, try to ask a switchboard (SB) for sending message
                        // NS: >>> XFR {id} SB
                        $this->writeln("XFR $this->id SB");
                        $this->debug_message("*** process ".$aMSNUsers[$nCurrentUser]." ($nCurrentUser/$nMSNUsers)");
                        continue;
                    }

                    // okay, process offline and other network user
                    // offline user first
                    $msn_network = 1;
                    $aMessage = $this->getMessage($sMessage, $msn_network, true);
                    foreach ($aOfflineUsers as $to) {
                        foreach ($aMessage as $message) {
                            $len = strlen($message);
                            $this->writeln("UUM $this->id $to $msn_network 1 $len");
                            $this->writedata($message);
                        }
                        $this->log_message("*** sent OIM to $to (network: $msn_network):\n$sMessage");
                    }
                    // other network
                    foreach ($aOtherUsers as $network => $aNetUsers) {
                        $aMessage = $this->getMessage($sMessage, $network);
                        foreach ($aNetUsers as $to) {
                            foreach ($aMessage as $message) {
                                $len = strlen($message);
                                $this->writeln("UUM $this->id $to $network 1 $len");
                                $this->writedata($message);
                            }
                            $this->log_message("*** sent to $to (network: $network):\n$sMessage");
                        }
                    }
                    $this->log_message("*** already send message from $process_file");
                    if ($backup_file) {
                        $backup_dir = dirname($_SERVER['argv'][0]).'/backup';
                        if (!file_exists($backup_dir))
                            @mkdir($backup_dir);
                        $backup_name = $backup_dir.'/'.strftime('%Y%m%d%H%M%S').'_'.$this->getpid().'_'.basename($process_file);
                        if (@rename($process_file, $backup_name))
                            $this->log_message("*** move file to $backup_name");
                    }
                    @unlink($process_file);
                    $process_file = false;
                    $sent = true;
                    $aMSNUsers = array();
                    $aOfflineUsers = array();
                    $aOtherUsers = array();
                    $nMSNUsers = 0;
                    break;

                case 'QNG':
                    // NS: <<< QNG {time}
                    @list(/* QNG */, $ping_wait) = @explode(' ', $data);
                    if ($ping_wait == 0) $ping_wait = 50;
                    if (is_int($use_ping) && $use_ping > 0) $ping_wait = $use_ping;
                    break;

                case 'RNG':
                    // someone is trying to talk to us
                    // NS: <<< RNG {session_id} {server} {auth_type} {ticket} {email} {alias} U {client} 0
                    @list(/* RNG */, $sid, $server, /* auth_type */, $ticket, $email, $name, ) = @explode(' ', $data);
                    @list($sb_ip, $sb_port) = @explode(':', $server);
                    $this->log_message("*** RING from $email, $sb_ip:$sb_port");
                    $this->switchboard_ring($sb_ip, $sb_port, $sid, $ticket, $my_function);
                    break;

                case 'OUT':
                    // force logout from NS
                    // NS: <<< OUT xxx
                    fclose($this->fp);
                    $this->log_message("*** LOGOUT from NS");
                    break;

                default:
                    if (is_numeric($code)) {
                        $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                        $this->debug_message("*** NS: $this->error");
                        if ($process_file !== false) {
                            // try login again...
                            // logout now
                            // NS: >>> OUT
                            $this->writeln("OUT");
                            fclose($this->fp);
                            $this->fp = false;
                            $this->log_message("!!! logout");
                        }
                    }
                    break;
            }
        }
    }

    function sendMessage($sMessage, $aTo)
    {
        if (!is_array($aTo)) {
            if ($aTo === '')
                $aTo = array();
            else
                $aTo = array($aTo);
        }
        // since 2009/07/21, no more SBS after USR, so we just do the following step here
        $this->writeln("CHG $this->id NLN");
        stream_set_timeout($this->fp, $this->stream_timeout);
        $quit = false;
        $online_cnt = 0;
        $offline_cnt = 0;
        $other_cnt = 0;
        $start_tm = time();
        while (!feof($this->fp)) {
            if ($quit) break;
            $data = $this->readln();
            // no data ?
            if ($data === false) {
                if ($this->timeout > 0) {
                    $now_tm = time();
                    $used_time = ($now_tm >= $start_tm) ? $now_tm - $start_tm : $now_tm;
                    if ($used_time > $this->timeout) {
                        $this->error = 'Timeout, maybe protocol changed!';
                        $this->debug_message("*** $this->error");
                        break;
                    }
                }
                continue;
            }
            $code = substr($data, 0, 3);
            $start_tm = time();
            switch ($code) {
                case 'SBS':
                    // after 'USR {id} OK {user} {verify} 0' response, the server will send SBS and profile to us
                    // NS: <<< SBS 0 null
                    // we don't need profile data, so just ignore it
                    // change our status to online first
                    // NS: >>> CHG {id} {status}
                    // ignore it now, since 2009/07/21, we won't receive this after receive USR
                    // $this->writeln("CHG $this->id NLN");
                    break;

                case 'ADL':
                    // randomly, we get ADL command, someome add us to their contact list for MSNP15
                    // NS: <<< ADL 0 {size}
                    @list(/* ADL */, /* 0 */, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0)
                        $data = $this->readdata($size);
                    break;

                case 'RML':
                    // randomly, we get RML command, someome remove us to their contact list for MSNP15
                    // NS: <<< RML 0 {size}
                    @list(/* RML */, /* 0 */, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0)
                        $data = $this->readdata($size);
                    break;

                case 'UBM':
                    // randomly, we get UBM, this is the message from other network, like Yahoo!
                    // NS: <<< UBM {from_email} $from_network $to_email $to_network $type {size}
                    @list(/* UBM */, $from_email, $from_network, $to_email, $to_network, $type, $size,) = @explode(' ', $data);
                    if (is_numeric($size) && $size > 0)
                        $data = $this->readdata($size);
                    break;

                case 'UBX':
                    // randomly, we get UBX notification from server
                    // NS: <<< UBX network:email {size}
                    @list(/* UBX */, /* network:email */, $size,) = @explode(' ', $data);
                    // we don't need the notification data, so just ignore it
                    if (is_numeric($size) && $size > 0)
                        $this->readdata($size);
                    break;

                case 'MSG':
                    // randomly, we get MSG notification from server
                    // NS: <<< MSG Hotmail Hotmail {size}
                    @list(/* MSG */, /* Hotmail */, /* Hotmail */, $size,) = @explode(' ', $data);
                    // we don't need the notification data, so just ignore it
                    if (is_numeric($size) && $size > 0)
                        $this->readdata($size);
                    break;

                case 'CHL':
                    // randomly, we'll get challenge from server
                    // NS: <<< CHL 0 {code}
                    @list(/* CHL */, /* 0 */, $chl_code,) = @explode(' ', $data);
                    $fingerprint = $this->getChallenge($chl_code);
                    // NS: >>> QRY {id} {product_id} 32
                    // NS: >>> fingerprint
                    $this->writeln("QRY $this->id $this->prod_id 32");
                    $this->writedata($fingerprint);
                    break;

                case 'CHG':
                    // NS: <<< CHG {id} {status} {code}
                    // ignore it

                    // if message is empty or To list is empty, just quit
                    if (count($aTo) == 0 || $sMessage === '') {
                        $quit = true;
                        break;
                    }

                    $aMSNUsers = array();
                    $aOfflineUsers = array();
                    $aOtherUsers = array();
                    $nMSNUsers = 0;
                    foreach ($aTo as $sUser) {
                        @list($u_user, $u_domain, $u_network) = @explode('@', $sUser);
                        if ($u_network === '' || $u_network == NULL)
                            $u_network = 1;
                        $to_email = trim($u_user.'@'.$u_domain);
                        if ($u_network == 1)
                            $aMSNUsers[$nMSNUsers++] = $to_email;
                        else
                            $aOtherUsers[$u_network][] = $to_email;
                    }

                    if ($nMSNUsers == 0) {
                        // no MSN account, only others
                        // process other network first
                        foreach ($aOtherUsers as $network => $aNetUsers) {
                            $other_cnt++;
                            $aMessage = $this->getMessage($sMessage, $network);
                            foreach ($aNetUsers as $to) {
                                foreach ($aMessage as $message) {
                                    $len = strlen($message);
                                    $this->writeln("UUM $this->id $to $network 1 $len");
                                    $this->writedata($message);
                                }
                                $this->debug_message("*** sent to $to (network: $network):\n$sMessage");
                            }
                        }
                        $quit = true;
                        break;
                    }

                    $nCurrentUser = 0;

                    // okay, try to ask a switchboard (SB) for sending message
                    // NS: >>> XFR {id} SB
                    $this->writeln("XFR $this->id SB");
                    break;

                case 'XFR':
                    // NS: <<< XFR {id} SB {server} CKI {cki} U messenger.msn.com 0
                    @list(/* XFR */, /* {id} */, /* SB */, $server, /* CKI */, $cki_code, /* ... */) = @explode(' ', $data);
                    @list($ip, $port) = @explode(':', $server);
                    $bSBresult = $this->switchboard_control($ip, $port, $cki_code, $aMSNUsers[$nCurrentUser], $sMessage);
                    if ($bSBresult === false) {
                        // error for switchboard
                        $this->debug_message("!!! error for sending message to ".$aMSNUsers[$nCurrentUser]);
                        $aOfflineUsers[] = $aMSNUsers[$nCurrentUser];
                    }
                    else
                        $online_cnt++;

                    $nCurrentUser++;
                    if ($nCurrentUser < $nMSNUsers) {
                        // for next user
                        // okay, try to ask a switchboard (SB) for sending message
                        // NS: >>> XFR {id} SB
                        $this->writeln("XFR $this->id SB");
                        continue;
                    }

                    // okay, process offline user
                    $msn_network = 1;
                    $aMessage = $this->getMessage($sMessage, $msn_network, true);
                    foreach ($aOfflineUsers as $to) {
                        foreach ($aMessage as $message) {
                            $len = strlen($message);
                            $this->writeln("UUM $this->id $to $msn_network 1 $len");
                            $this->writedata($message);
                        }
                        $this->log_message("*** sent OIM to $to (network: $msn_network):\n$sMessage");
                    }
                    $quit = true;
                    break;

                default:
                    if (is_numeric($code)) {
                        $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                        $this->debug_message("*** NS: $this->error");
                    }
                    break;
            }
        }
        // logout now
        // NS: >>> OUT
        $this->writeln("OUT");
        fclose($this->fp);
        return array(
                        'online' => $online_cnt,
                        'offline' => $offline_cnt,
                        'others' => $other_cnt
                    );
    }

    function getChallenge($code)
    {
        // http://msnpiki.msnfanatic.com/index.php/MSNP11:Challenges
        // Step 1: The MD5 Hash
        $md5Hash = md5($code.$this->prod_key);
        $aMD5 = @explode("\0", chunk_split($md5Hash, 8, "\0"));
        for ($i = 0; $i < 4; $i++) {
            $aMD5[$i] = implode('', array_reverse(@explode("\0", chunk_split($aMD5[$i], 2, "\0"))));
            $aMD5[$i] = (0 + base_convert($aMD5[$i], 16, 10)) & 0x7FFFFFFF;
        }

        // Step 2: A new string
        $chl_id = $code.$this->prod_id;
        $chl_id .= str_repeat('0', 8 - (strlen($chl_id) % 8));

        $aID = @explode("\0", substr(chunk_split($chl_id, 4, "\0"), 0, -1));
        for ($i = 0; $i < count($aID); $i++) {
            $aID[$i] = implode('', array_reverse(@explode("\0", chunk_split($aID[$i], 1, "\0"))));
            $aID[$i] = 0 + base_convert(bin2hex($aID[$i]), 16, 10);
        }

        // Step 3: The 64 bit key
        $magic_num = 0x0E79A9C1;
        $str7f = 0x7FFFFFFF;
        $high = 0;
        $low = 0;
        for ($i = 0; $i < count($aID); $i += 2) {
            $temp = $aID[$i];
            $temp = bcmod(bcmul($magic_num, $temp), $str7f);
            $temp = bcadd($temp, $high);
            $temp = bcadd(bcmul($aMD5[0], $temp), $aMD5[1]);
            $temp = bcmod($temp, $str7f);

            $high = $aID[$i+1];
            $high = bcmod(bcadd($high, $temp), $str7f);
            $high = bcadd(bcmul($aMD5[2], $high), $aMD5[3]);
            $high = bcmod($high, $str7f);

            $low = bcadd(bcadd($low, $high), $temp);
        }

        $high = bcmod(bcadd($high, $aMD5[1]), $str7f);
        $low = bcmod(bcadd($low, $aMD5[3]), $str7f);

        $new_high = bcmul($high & 0xFF, 0x1000000);
        $new_high = bcadd($new_high, bcmul($high & 0xFF00, 0x100));
        $new_high = bcadd($new_high, bcdiv($high & 0xFF0000, 0x100));
        $new_high = bcadd($new_high, bcdiv($high & 0xFF000000, 0x1000000));
        // we need integer here
        $high = 0+$new_high;

        $new_low = bcmul($low & 0xFF, 0x1000000);
        $new_low = bcadd($new_low, bcmul($low & 0xFF00, 0x100));
        $new_low = bcadd($new_low, bcdiv($low & 0xFF0000, 0x100));
        $new_low = bcadd($new_low, bcdiv($low & 0xFF000000, 0x1000000));
        // we need integer here
        $low = 0+$new_low;

        // we just use 32 bits integer, don't need the key, just high/low
        // $key = bcadd(bcmul($high, 0x100000000), $low);

        // Step 4: Using the key
        $md5Hash = md5($code.$this->prod_key);
        $aHash = @explode("\0", chunk_split($md5Hash, 8, "\0"));

        $hash = '';
        $hash .= sprintf("%08x", (0 + base_convert($aHash[0], 16, 10)) ^ $high);
        $hash .= sprintf("%08x", (0 + base_convert($aHash[1], 16, 10)) ^ $low);
        $hash .= sprintf("%08x", (0 + base_convert($aHash[2], 16, 10)) ^ $high);
        $hash .= sprintf("%08x", (0 + base_convert($aHash[3], 16, 10)) ^ $low);

        return $hash;
    }

    function getMessage($sMessage, $network = 1, $is_oim = false)
    {
        if ($is_oim)
            $oim_header = "Dest-Agent: client\r\n";
        else
            $oim_header = '';
        $msg_header = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nX-MMS-IM-Format: FN=$this->font_fn; EF=$this->font_ef; CO=$this->font_co; CS=$this->font_cs; PF=$this->font_pf\r\n$oim_header\r\n";
        $msg_header_len = strlen($msg_header);
        if ($network == 1) {
            if ($is_oim)
                $maxlen = $this->max_oim_message_len - $msg_header_len;
            else
                $maxlen = $this->max_msn_message_len - $msg_header_len;
        }
        else
            $maxlen = $this->max_yahoo_message_len - $msg_header_len;
        $aMessage = array();
        $aStr = @explode("\n", $sMessage);
        $cur_len = 0;
        $msg = '';
        $add_crlf = false;
        foreach ($aStr as $str) {
            $str = str_replace("\r", '', $str);
            $len = strlen($str);
            while ($len > $maxlen) {
                if ($cur_len > 0) {
                    // already has header/msg
                    $aMessage[] = $msg_header.$msg;
                    $cur_len = 0;
                    $msg = '';
                    $add_crlf = false;
               }
                $aMessage[] = $msg_header.substr($str, 0, $maxlen);
                $str = substr($str, $maxlen);
                $len = strlen($str);
            }
            if (($cur_len + $len) > $maxlen) {
                $aMessage[] = $msg_header.$msg;
                $cur_len = 0;
                $msg = '';
                $add_crlf = false;
            }
            if ($msg !== '' || $add_crlf) {
                $msg .= "\r\n";
                $cur_len += 2;
            }
            $add_crlf = true;
            $msg .= $str;
            $cur_len += $len;
        }
        if ($cur_len != 0)
            $aMessage[] = $msg_header.$msg;
        return $aMessage;
    }

    function switchboard_control($ip, $port, $cki_code, $sTo, $sMessage)
    {
        $this->debug_message("*** SB: try to connect to switchboard server $ip:$port");
        $this->sb = @fsockopen($ip, $port, $errno, $errstr, 5);
        if (!$this->sb) {
            $this->error = "SB: Can't connect to $ip:$port, error => $errno, $errstr";
            $this->debug_message("*** $this->error");
            return false;
        }

        $user = $sTo;
        stream_set_timeout($this->sb, $this->stream_timeout);
        // SB: >>> USR {id} {user};{guid} {cki}
        $this->sb_writeln("USR $this->sbid $this->user;$this->machine_guid $cki_code");

        $sent = false;
        $start_tm = time();
        $got_error = false;
        $offline = false;
        while (!feof($this->sb)) {
            if ($sent || $offline) break;
            $data = $this->sb_readln();
            if ($data === false) {
                if ($this->timeout > 0) {
                    $now_tm = time();
                    $used_time = ($now_tm >= $start_tm) ? $now_tm - $start_tm : $now_tm;
                    if ($used_time > $this->timeout) {
                        $this->error = 'Timeout, maybe protocol changed!';
                        $this->debug_message("*** $this->error");
                        $got_error = true;
                        break;
                    }
                }
                continue;
            }
            $code = substr($data, 0, 3);
            $start_tm = time();
            switch($code) {
                case 'USR':
                    // SB: <<< USR {id} OK {user} {alias}
                    // we don't need the data, just ignore it
                    // request user to join this switchboard
                    // include myself
                    // SB: >>> CAL {id} {me}
                    $this->sb_writeln("CAL $this->sbid $this->user");
                    // SB: >>> CAL {id} {user}
                    $this->sb_writeln("CAL $this->sbid $user");
                    break;

                case 'CAL':
                    // SB: <<< CAL {id} RINGING {?}
                    // we don't need this, just ignore, and wait for other response
                    break;

                case '217':
                    // SB: <<< 217 {id}
                    // if user isn't online or no such user, we will get 217.
                    // switchboard can't send message if he isn't online
                    $this->debug_message("*** SB: $user offline! skip to send message!");
                    $offline = true;
                    break;

                case 'JOI':
                    // SB: <<< JOI {user} {alias} {clientcapab?}
                    // someone join us
                    // there will have more than one record for same user (multiple login in MSNP18)
                    // so we need to wait until the one without machine guid
                    @list(/* JOI */, $joi_user, $joi_alias, $joi_capab) = @explode(' ', $data);
                    if ($joi_user != $user) {
                        $this->debug_message("*** SB: skip, not finished yet");
                        break;
                    }
                    // we don't need the data, just ignore it
                    // no more user here
                    if ($this->send_typing) {
                        $sTypingMsg = "MIME-Version: 1.0\r\nContent-Type: text/x-msmsgscontrol\r\nTypingUser: $this->user\r\n\r\n\r\n";
                        $len = strlen($sTypingMsg);
                        $this->sb_writeln("MSG $this->sbid U $len");
                        $this->sb_writedata($sTypingMsg);
                    }
                    $aMessage = $this->getMessage($sMessage);
                    foreach ($aMessage as $message) {
                        $len = strlen($message);
                        $this->sb_writeln("MSG $this->sbid A $len");
                        $this->sb_writedata($message);
                    }
                    break;

                case 'ACK':
                    $this->debug_message("*** SB: got ACK, the message already sent!");
                    $sent = true;
                    break;

                case 'NAK':
                    $this->debug_message("*** SB: got NAK, the message not sent, try offline again!");
                    $offline = true;
                    break;

                default:
                    if (is_numeric($code)) {
                        $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                        $this->debug_message("*** SB: $this->error");
                        $got_error = true;
                    }
                    break;
            }
        }
        if (feof($this->sb)) {
            // lost connection? error? try OIM later
            @fclose($this->sb);
            return false;
        }
        $this->sb_writeln("OUT");
        @fclose($this->sb);
        if ($offline || $got_error) return false;
        return true;
    }

    function switchboard_ring($ip, $port, $sid, $ticket, $my_function)
    {
        $this->debug_message("*** SB: try to connect to switchboard server $ip:$port");
        $this->sb = @fsockopen($ip, $port, $errno, $errstr, 5);
        if (!$this->sb) {
            $this->error = "SB: Can't connect to $ip:$port, error => $errno, $errstr";
            $this->debug_message("*** $this->error");
            $this->log_message("!!! $this->error");
            return false;
        }

        stream_set_timeout($this->sb, $this->stream_timeout);
        // SB: >>> ANS {id} {user};{guid} {ticket} {session_id}
        $this->sb_writeln("ANS $this->sbid $this->user;$this->machine_guid $ticket $sid");

        $ignore = false;
        $start_tm = time();
        while (!feof($this->sb)) {
            $data = $this->sb_readln();
            if ($data === false) {
                if ($this->timeout > 0) {
                    $now_tm = time();
                    $used_time = ($now_tm >= $start_tm) ? $now_tm - $start_tm : $now_tm;
                    if ($used_time > $this->timeout) {
                        if ($ignore == false) {
                            $this->error = 'Timeout, maybe protocol changed!';
                            $this->debug_message("*** $this->error");
                            $this->log_message("!!! $this->error");
                        }
                        $this->sb_writeln("OUT");
                        fclose($this->sb);
                        $this->sb = false;
                        return false;
                    }
                }
                continue;
            }
            $code = substr($data, 0, 3);
            $start_tm = time();
            switch($code) {
                case 'IRO':
                    // SB: <<< IRO {id} {rooster} {roostercount} {email} {alias} {clientcapab}
                    @list(/* IRO */, /* id */, $cur_num, $total, $email, $alias, $clientcapab) = @explode(' ', $data);
                    $this->log_message("*** $email join us");
                    break;

                case 'ANS':
                    // SB: <<< ANS {id} OK
                    // ignore this
                    break;

                case 'JOI':
                    // SB: <<< JOI {email} {alias} {clientcapab}
                    // myself?
                    // ignore this
                    break;

                case 'BYE':
                    $this->log_message("*** Quit for BYE");
                    $this->sb_writeln("OUT");
                    fclose($this->sb);
                    $this->sb = false;
                    return true;

                case 'MSG':
                    // SB: <<< MSG {email} {alias} {len}
                    @list(/* MSG */, $from_email, /* alias */, $len, ) = @explode(' ', $data);
                    $len = trim($len);
                    $data = $this->sb_readdata($len);
                    $aLines = @explode("\n", $data);
                    $header = true;
                    $ignore = false;
                    $is_p2p = false;
                    $sMsg = '';
                    foreach ($aLines as $line) {
                        $line = rtrim($line);
                        if ($header) {
                            if ($line === '') {
                                $header = false;
                                continue;
                            }
                            if (strncasecmp($line, 'TypingUser:', 11) == 0) {
                                // typing notification, just ignore
                                $ignore = true;
                                break;
                            }
                            if (strncasecmp($line, 'Chunk:', 6) == 0) {
                                // we don't handle any split message, just ignore
                                $ignore = true;
                                break;
                            }
                            if (strncasecmp($line, 'Content-Type: application/x-msnmsgrp2p', 38) == 0) {
                                // p2p message, ignore it, but we need to send acknowledgement for it...
                                $is_p2p = true;
                                $p = strstr($data, "\n\n");
                                $sMsg = '';
                                if ($p === false) {
                                    $p = strstr($data, "\r\n\r\n");
                                    if ($p !== false)
                                        $sMsg = substr($p, 4);
                                }
                                else
                                    $sMsg = substr($p, 2);
                                break;
                            }
                            if (strncasecmp($line, 'Content-Type: application/x-', 28) == 0) {
                                // ignore all application/x-... message
                                // for example:
                                //      application/x-ms-ink        => ink message
                                $ignore = true;
                                break;
                            }
                            if (strncasecmp($line, 'Content-Type: text/x-', 21) == 0) {
                                // ignore all text/x-... message
                                // for example:
                                //      text/x-msnmsgr-datacast         => nudge, voice clip....
                                //      text/x-mms-animemoticon         => customized animemotion word
                                $ignore = true;
                                break;
                            }
                            continue;
                        }
                        if ($sMsg !== '')
                            $sMsg .= "\n";
                        $sMsg .= $line;
                    }
                    if ($ignore) {
                        $this->log_message("*** ingnore from $from_email: $line");
                        break;
                    }
                    if ($is_p2p) {
                        // we will ignore any p2p message after sending acknowledgement
                        $ignore = true;
                        $len = strlen($sMsg);
                        $this->log_message("*** p2p message from $from_email, size $len");
                        // header = 48 bytes
                        // content >= 0 bytes
                        // footer = 4 bytes
                        // so it need to >= 52 bytes
                        if ($len < 52) {
                            $this->log_message("*** p2p: size error, less than 52!");
                            break;
                        }
                        $aDwords = @unpack("V12dword", $sMsg);
                        if (!is_array($aDwords)) {
                            $this->log_message("*** p2p: header unpack error!");
                            break;
                        }
                        $this->debug_message("*** p2p: dump received message:\n".$this->dump_binary($sMsg));
                        $hdr_SessionID = $aDwords['dword1'];
                        $hdr_Identifier = $aDwords['dword2'];
                        $hdr_DataOffsetLow = $aDwords['dword3'];
                        $hdr_DataOffsetHigh = $aDwords['dword4'];
                        $hdr_TotalDataSizeLow = $aDwords['dword5'];
                        $hdr_TotalDataSizeHigh = $aDwords['dword6'];
                        $hdr_MessageLength = $aDwords['dword7'];
                        $hdr_Flag = $aDwords['dword8'];
                        $hdr_AckID = $aDwords['dword9'];
                        $hdr_AckUID = $aDwords['dword10'];
                        $hdr_AckSizeLow = $aDwords['dword11'];
                        $hdr_AckSizeHigh = $aDwords['dword12'];
                        $this->debug_message("*** p2p: header SessionID = $hdr_SessionID");
                        $this->debug_message("*** p2p: header Inentifier = $hdr_Identifier");
                        $this->debug_message("*** p2p: header Data Offset Low = $hdr_DataOffsetLow");
                        $this->debug_message("*** p2p: header Data Offset High = $hdr_DataOffsetHigh");
                        $this->debug_message("*** p2p: header Total Data Size Low = $hdr_TotalDataSizeLow");
                        $this->debug_message("*** p2p: header Total Data Size High = $hdr_TotalDataSizeHigh");
                        $this->debug_message("*** p2p: header MessageLength = $hdr_MessageLength");
                        $this->debug_message("*** p2p: header Flag = $hdr_Flag");
                        $this->debug_message("*** p2p: header AckID = $hdr_AckID");
                        $this->debug_message("*** p2p: header AckUID = $hdr_AckUID");
                        $this->debug_message("*** p2p: header AckSize Low = $hdr_AckSizeLow");
                        $this->debug_message("*** p2p: header AckSize High = $hdr_AckSizeHigh");

                        if ($hdr_Flag == 2) {
                            // just send ACK...
                            $this->sb_writeln("ACK $this->sbid");
                            break;
                        }
                        if ($hdr_SessionID == 4 && $hdr_Flag == 2) {
                            // ignore?
                            $this->debug_message("*** p2p: ignore flag 4");
                            break;
                        }
                        $finished = false;
                        if ($hdr_TotalDataSizeHigh == 0) {
                            // only 32 bites size
                            if (($hdr_MessageLength + $hdr_DataOffsetLow) == $hdr_TotalDataSizeLow)
                                $finished = true;
                        }
                        else {
                            // we won't accept any file transfer
                            // so I think we won't get any message size need to use 64 bits
                            // 64 bits size here, can't count directly...
                            $totalsize = base_convert(sprintf("%X%08X", $hdr_TotalDataSizeHigh, $hdr_TotalDataSizeLow), 16, 10);
                            $dataoffset = base_convert(sprintf("%X%08X", $hdr_DataOffsetHigh, $hdr_DataOffsetLow), 16, 10);
                            $messagelength = base_convert(sprintf("%X", $hdr_MessageLength), 16, 10);
                            $now_size = bcadd($dataoffset, $messagelength);
                            if (bccomp($now_size, $totalsize) >= 0)
                                $finished = true;
                        }
                        if (!$finished) {
                            // ignore not finished split packet
                            $this->debug_message("*** p2p: ignore split packet, not finished");
                            break;
                        }
                        $new_id = ~$hdr_Identifier;
                        $hdr = pack("VVVVVVVVVVVV", $hdr_SessionID,
                                                    $new_id,
                                                    0, 0,
                                                    $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh,
                                                    0,
                                                    2,
                                                    $hdr_Identifier,
                                                    $hdr_AckID,
                                                    $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh);
                        $footer = pack("V", 0);
                        $message = "MIME-Version: 1.0\r\nContent-Type: application/x-msnmsgrp2p\r\nP2P-Dest: $from_email\r\n\r\n$hdr$footer";
                        $len = strlen($message);
                        $this->sb_writeln("MSG $this->sbid D $len");
                        $this->sb_writedata($message);
                        $this->log_message("*** p2p: send acknowledgement for $hdr_SessionID");
                        $this->debug_message("*** p2p: dump sent message:\n".$this->dump_binary($hdr.$footer));
                        break;
                    }
                    $this->log_message("*** MSG from $from_email: $sMsg");
                    if ($my_function && function_exists($my_function) && is_callable($my_function)) {
                        $reply_msg = $my_function($from_email, $sMsg);
                        if ($reply_msg !== '') {
                            if ($this->send_typing) {
                                $message = "MIME-Version: 1.0\r\nContent-Type: text/x-msmsgscontrol\r\nTypingUser: $this->user\r\n\r\n\r\n";
                                $len = strlen($message);
                                $this->sb_writeln("MSG $this->sbid U $len");
                                $this->sb_writedata($message);
                            }
                            $aMessage = $this->getMessage($reply_msg);
                            foreach ($aMessage as $message) {
                                $len = strlen($message);
                                $this->sb_writeln("MSG $this->sbid A $len");
                                $this->sb_writedata($message);
                            }
                            $this->log_message("Response to $from_email:\n$reply_msg");
                        }
                    }
                    break;

                case 'ACK':
                    $this->debug_message("*** SB: got ACK, the message already sent!");
                    $this->sb_writeln("OUT");
                    fclose($this->sb);
                    $this->sb = false;
                    return true;

                case 'NAK':
                    $this->debug_message("*** SB: got NAK, the message not sent!");
                    $this->sb_writeln("OUT");
                    fclose($this->sb);
                    $this->sb = false;
                    return true;

                default:
                    if (is_numeric($code)) {
                        $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                        $this->debug_message("*** SB: $this->error");
                    }
                    break;
            }
        }
        return true;
    }

    // read data for specified size
    function readdata($size)
    {
        $data = '';
        $count = 0;
        while (!feof($this->fp)) {
            $buf = @fread($this->fp, $size - $count);
            $data .= $buf;
            $count += strlen($buf);
            if ($count >= $size) break;
        }
        $this->debug_message("NS: data ($size/$count) <<<\n$data");
        return $data;
    }

    // read one line
    function readln()
    {
        $data = @fgets($this->fp, 4096);
        if ($data !== false) {
            $data = trim($data);
            $this->debug_message("NS: <<< $data");
        }
        return $data;
    }

    // write to server, append \r\n, also increase id
    function writeln($data)
    {
        @fwrite($this->fp, $data."\r\n");
        $this->debug_message("NS: >>> $data");
        $this->id++;
        return;
    }

    // write data to server
    function writedata($data)
    {
        @fwrite($this->fp, $data);
        $this->debug_message("NS: data >>> $data");
        return;
    }

    // read data for specified size for SB
    function sb_readdata($size)
    {
        $data = '';
        $count = 0;
        while (!feof($this->sb)) {
            $buf = @fread($this->sb, $size - $count);
            $data .= $buf;
            $count += strlen($buf);
            if ($count >= $size) break;
        }
        $this->debug_message("SB: data ($size/$count) <<<\n$data");
        return $data;
    }

    // read one line for SB
    function sb_readln()
    {
        $data = @fgets($this->sb, 4096);
        if ($data !== false) {
            $data = trim($data);
            $this->debug_message("SB: <<< $data");
        }
        return $data;
    }

    // write to server for SB, append \r\n, also increase id
    // switchboard server only accept \r\n, it will lost connection if just \n only
    function sb_writeln($data)
    {
        @fwrite($this->sb, $data."\r\n");
        $this->debug_message("SB: >>> $data");
        $this->sbid++;
        return;
    }

    // write data to server
    function sb_writedata($data)
    {
        @fwrite($this->sb, $data);
        $this->debug_message("SB: data >>> $data");
        return;
    }

    // show debug information
    function debug_message($str)
    {
        if (!$this->debug) return;
        if ($this->log_file !== '') {
            $fp = fopen($this->log_file, 'at');
            if ($fp) {
                fputs($fp, strftime('%m/%d/%y %H:%M:%S').' ['.$this->getpid().'] '.$str."\n");
                fclose($fp);
                return;
            }
            // still show debug information, if we can't open log_file
        }
        echo $str."\n";
        return;
    }

    function dump_binary($str)
    {
        $buf = '';
        $a_str = '';
        $h_str = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            if (($i % 16) == 0) {
                if ($buf !== '') {
                    $buf .= "$h_str $a_str\n";
                }
                $buf .= sprintf("%04X:", $i);
                $a_str = '';
                $h_str = '';
            }
            $ch = ord($str[$i]);
            if ($ch < 32)
                $a_str .= '.';
            else
                $a_str .= chr($ch);
            $h_str .= sprintf(" %02X", $ch);
        }
        if ($h_str !== '')
            $buf .= "$h_str $a_str\n";
        return $buf;
    }

    // write log
    function log_message($str)
    {
        if (!$this->log_path)
            $this->log_path = dirname($_SERVER['argv'][0]);
        $fname = $this->log_path.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR.'msn_'.strftime('%Y%m%d').'.log';
        $fp = fopen($fname, 'at');
        if ($fp) {
            fputs($fp, strftime('%m/%d/%y %H:%M:%S').' ['.$this->getpid().'] '.$str."\n");
            fclose($fp);
        }
        $this->debug_message($str);
        return;
    }

    // get current process id
    function getpid()
    {
        if ($this->windows) return 'nopid';
        return posix_getpid();
    }
}

?>