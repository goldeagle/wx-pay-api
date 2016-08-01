<?php
/**
 * 微信支付配置文件
 */
return [
    'wxpay' => [
        'APPID' => '你的微信公众号 APPID',
        'MCHID' => '你的微信商户账号',
        'KEY' => '在微信公众号中设置的APPKEY',
        'APPSECRET' => '微信密钥',
        'NOTIFY_URL' => '你接收微信异步返回支付消息的网址',
        'SSLCERT_PATH' => '../cert/apiclient_cert.pem',
        'SSLKEY_PATH' => '../cert/apiclient_key.pem',
        'REPORT_LEVENL' => 1
    ]
];