<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language' => 'ru-Ru',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'asd',
            'baseUrl' => '',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
                'multipart/form-data' => 'yii\web\MultipartFormDataParser',
            ]
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
            'enableSession' => false,

        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                // ['class' => 'yii\rest\UrlRule', 'controller' => 'user'],
                'OPTIONS  <prefix:.*>/registration' => 'user/options',
                'POST <prefix:.*>/registration' => 'user/register',

                'OPTIONS /authorization' => 'user/options',
                'POST /authorization' => 'user/login',

                'OPTIONS /logout' => 'user/options',
                'GET /logout' => 'user/logout',
                [
                    'pluralize' => true,
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'file',
                    'extraPatterns' => [
                        'OPTIONS /' => 'edd-file',
                        'POST /' => 'edd-file',

                        'OPTIONS disk' => 'check-all',
                        'GET disk' => 'check-all', 
                        'GET shared' => 'check-access', 


                        'OPTIONS <file_id>' => 'get-file',
                        'GET <file_id>' => 'get-file',

                        'DELETE <file_id>' => 'delete-file',
                        'PATCH <file_id>' => 'rename-file',

                        'POST <file_id>/access' => 'add-co',
                        'DELETE <file_id>/access' => 'delete-co',




                    ]
                ]
            ],
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'formatters' => [
                \yii\web\Response::FORMAT_JSON => [
                    'class' => 'yii\web\JsonResponseFormatter',
                    'prettyPrint' => YII_DEBUG, // use "pretty" output in debug mode
                    'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ],
            ],
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                $response = $event->sender;
                if ($response->statusCode === 404) {
                    $response->data = [
                        'code' => 404,
                        'message' => 'not found'
                    ];
                    $response->statusCode = 404;
                }
            },
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];
}

return $config;
