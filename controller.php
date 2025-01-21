<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $domain = $_POST['domain'] ?? '';
    $plan = $_POST['plan'] ?? '';

    if (!$name || !$email || !$login || !$password || !$domain || !$plan) {
        responseJson(false, 'Todos los campos son requeridos');
    }

    $pleskHost = 'https://192.168.122.248:8443/enterprise/control/agent.php';
    $pleskUser = 'admin';
    $pleskPassword = 'vandreu123';

    $clientXml = createClientXml($name, $email, $login, $password);
    $clientResponse = sendPleskRequest($pleskHost, $clientXml, $pleskUser, $pleskPassword);
    file_put_contents('plesk_api_response.log', "Cliente Response:\n" . print_r($clientResponse, true), FILE_APPEND);

    if (!strpos($clientResponse, '<status>ok</status>')) {
        responseJson(false, 'Error al crear el cliente');
    }

    $clientId = extractXmlValue($clientResponse, 'id');
    $domainXml = createDomainXml($domain, $login, $password, $clientId, $plan);
    $domainResponse = sendPleskRequest($pleskHost, $domainXml, $pleskUser, $pleskPassword);
    file_put_contents('plesk_api_response.log', "Dominio Response:\n" . print_r($domainResponse, true), FILE_APPEND);

    if (strpos($domainResponse, '<status>ok</status>')) {
        responseJson(true, 'Cliente, dominio y suscripción creados correctamente');
    } else {
        responseJson(false, 'Error al crear el dominio o suscripción');
    }
}

function responseJson($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

function createClientXml($name, $email, $login, $password)
{
    return "<?xml version='1.0' encoding='UTF-8'?>
    <packet>
        <customer>
            <add>
                <gen_info>
                    <pname>$name</pname>
                    <login>$login</login>
                    <passwd>$password</passwd>
                    <email>$email</email>
                </gen_info>
            </add>
        </customer>
    </packet>";
}

function createDomainXml($domain, $login, $password, $clientId, $plan)
{
    return "<?xml version='1.0' encoding='UTF-8'?>
    <packet>
        <webspace>
            <add>
                <gen_setup>
                    <name>$domain</name>
                    <ip_address>192.168.122.248</ip_address>
                    <owner-id>$clientId</owner-id>
                    <htype>vrt_hst</htype>
                </gen_setup>
                <hosting>
                    <vrt_hst>
                        <property>
                            <name>ftp_login</name>
                            <value>$login</value>
                        </property>
                        <property>
                            <name>ftp_password</name>
                            <value>$password</value>
                        </property>
                    </vrt_hst>
                </hosting>
                <plan-name>$plan</plan-name>
            </add>
        </webspace>
    </packet>";
}

function sendPleskRequest($url, $xmlData, $username, $password)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "HTTP_AUTH_LOGIN: $username",
        "HTTP_AUTH_PASSWD: $password",
        "Content-Type: text/xml"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        responseJson(false, 'Error de cURL: ' . curl_error($ch));
    }
    curl_close($ch);
    return $response;
}

function extractXmlValue($xml, $tag)
{
    $simpleXml = simplexml_load_string($xml);
    return (string) $simpleXml->xpath("//$tag")[0];
}