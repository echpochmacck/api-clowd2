<?php

namespace app\controllers;

use yii\filters\Cors;
use yii\filters\auth\HttpBearerAuth;
use app\models\User;
use yii;

class UserController extends \yii\rest\ActiveController
{
    public function actionIndex()
    {
        // return $this->render('index');
    }
    public $enableCsrfValidation = false;
    public $modelClass = '';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $auth = $behaviors['authenticator'];
        unset($behaviors['authenticator']);
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => [(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://' . $_SERVER['REMOTE_ADDR'])],
                'Access-Control-Request-Method' => ['POST', 'PUT', 'GET', 'PATCH', 'DELETE'],
                'Access-Control-Request-Headers' => ['Content-type', 'Authorization'],
            ],
            'actions' => [
                'logout' =>
                [
                    'Access-Control-Allow-Creditionals' => true,
                ]
            ]
        ];
        $auth = [
            'class' => HttpBearerAuth::class,
            'only' => ['logout'],
            'optional' => ['logout'],

        ];
        $behaviors['authenticator'] = $auth;
        return $behaviors;
    }

    public function actionRegister()
    {
        $data = yii::$app->request->post();
        $model = new User();
        $model->load($data, '');
        $model->scenario = 'register';
        $model->validate();
        if (!$model->hasErrors()) {
            $model->token = Yii::$app->security->generateRandomString();
            while (!$model->validate()) {
                $model->token = Yii::$app->security->generateRandomString();
            }

            $model->password = Yii::$app->getSecurity()->generatePasswordHash($model->password);

            if ($model->save(false)) {
                Yii::$app->response->statusCode = 200;
                $result = [
                    'success' => true,
                    'token' => $model->token,
                    'message' => 'Success',
                    'code' => 200
                ];
            }
        } else {

            Yii::$app->response->statusCode = 422;
            $result = [
                'success' => false,
                'errors' => $model->errors,
                'code' => 422
            ];
        }

        return $this->asJson($result);
    }

    public function actionLogin()
    {
        $data = yii::$app->request->post();
        $model = new User();
        $model->load($data, '');
        if (!$model->hasErrors()) {

            $user = User::findOne(['email' => $model->email]);
            if (!empty($user) && $user->validatePassword($model->password)) {
                $model = $user;
                // var_dump($model);die;
                $model->token = Yii::$app->security->generateRandomString();
                while (!$model->save()) {
                    $model->token = Yii::$app->security->generateRandomString();
                }

                Yii::$app->response->statusCode = 200;
                $result = [
                    'success' => true,
                    'token' => $model->token,
                    'message' => 'Success',
                    'code' => 200
                ];
            } else {
                Yii::$app->response->statusCode = 401;
                $result = [
                    'success' => false,
                    "message" => "Authorization failed",
                    'code' => 401
                ];
            }
        } else {
            Yii::$app->response->statusCode = 422;
            $result = [
                'success' => false,
                'errors' => $model->errors,
                'code' => 422
            ];
        }
        return $result;
    }

    public function actionLogout()
    {

        $identity = yii::$app->user->identity;
        // var_dump($identity);die;
        if ($identity) {
            $model = User::findOne($identity->id);
            $model->token = null;
            $model->save(false);
            Yii::$app->response->statusCode = 204;
            Yii::$app->response->send();
        } else {
            Yii::$app->response->statusCode = 403;
            return [
                'message' => 'Login failed'
            ];
        }
    }
}
