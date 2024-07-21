<?php

namespace app\controllers;

use yii\web\UploadedFile;
use yii\filters\Cors;
use yii\filters\auth\HttpBearerAuth;
use app\models\Authors;
use app\models\File;
use app\models\User;
use app\models\Role;
use PharIo\Manifest\Author;
use yii;
use yii\db\Query;

class FileController extends \yii\rest\ActiveController
{
    public function actionIndex()
    {
        return $this->render('index');
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
                'Access-Control-Request-Method' => ['POST', 'PUT', 'GET', 'PATCH', 'DELETE', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['Content-type', 'Authorization'],
            ],
            'actions' => [
                'edd-file' =>
                [
                    'Access-Control-Allow-Creditionals' => true,
                ],
                'delete-file' =>
                [
                    'Access-Control-Allow-Creditionals' => true,
                ],
                'rename-file' =>
                [
                    'Access-Control-Allow-Creditionals' => true,
                ],
                'add-co' =>
                [
                    'Access-Control-Allow-Creditionals' => true,
                ],
                'delete-co' => [
                    'Access-Control-Allow-Creditionals' => true,
                ],
            ],
            'check-all' => [
                'Access-Control-Allow-Creditionals' => true,
            ],
            'check-access' => [
                'Access-Control-Allow-Creditionals' => true,
            ],


        ];
        $auth = [
            'class' => HttpBearerAuth::class,
            'only' => ['edd-file', 'get-file', 'delete-file', 'rename-file', 'add-co', 'delete-co', 'check-all', 'check-access'],
            'optional' => ['edd-file', 'get-file', 'delete-file', 'rename-file', 'add-co', 'delete-co', 'check-all', 'check-access'],

        ];
        $behaviors['authenticator'] = $auth;
        return $behaviors;
    }

    public function actionEddFile()
    {
        $result = [];
        $identity = yii::$app->user->identity;
        if ($identity) {
            $files = uploadedFile::getInstancesByName('files');
            foreach ($files as $file) {
                $model = new File();
                $model->file = $file;
                $model->name = $model->file->baseName;
                $model->validate();
                if (!$model->hasErrors()) {
                    $model->extension = $model->file->extension;
                    $model->file_id = yii::$app->security->generateRandomString(10);
                    $i = 1;
                    if (!$model->isUniqueName($model->name, $identity->id)) {
                        // cоздания уникального имени для файла
                        $name = $model->name;
                        while (!$model->isUniqueName($name, $identity->id)) {
                            $name = $model->name . "($i)." . $model->extension;
                            $i++;
                        }
                        $model->name = $name;
                    }
                    while (!$model->validate()) {
                        $model->file_id = yii::$app->security->generateRandomString(10);
                    }
                    $model->url = yii::$app->request->getHostInfo() . '/files/' . $model->file_id;

                    if ($model->save(false)) {

                        // создания записи в сводной таблицы юзер-файл-роль
                        $author = new Authors();
                        $author->file_id = File::findOne(['file_id' => $model->file_id])->id;
                        $author->user_id = $identity->id;
                        $author->role_id = 1;

                        $model->url = Yii::$app->request->getHostInfo() . '/files/' . $model->file_id;
                        $dir = Yii::getAlias('@app/uploads/');

                        if (!file_exists($dir)) {
                            mkdir($dir, 0777, true);
                        }

                        if ($file->saveAs($dir . $model->file_id . '.' . $model->extension)) {
                            $author->save(false);
                            $result[] = [
                                'success' => true,
                                'code' => 200,
                                'name' => $model->name,
                                'url' => $model->url,
                                'file_id' => $model->file_id,
                            ];
                        }
                    } else {
                        $model->delete();
                    }
                } else {
                    $result[] = [
                        'success' => false,
                        'message' => $model->errors,
                        'name' => $model->name,
                    ];
                }
            }
        } else {
            Yii::$app->response->statusCode = 403;
            $result[] =  [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }

    public function actionGetFile($file_id = null)
    {
        $result = [];
        $identity = yii::$app->user->identity;
        if ($identity) {
            $file = File::findOne(['file_id' => $file_id]);
            if ($file) {
                $author = Authors::findOne(['file_id' => $file->id, 'user_id' => $identity->id]);
                if ($author) {
                    $dir = Yii::getAlias('@app/uploads/' . $file->file_id . '.' . $file->extension);
                   
                    if (file_exists($dir)) {
                        Yii::$app->response->statusCode = 200;
                        Yii::$app->response->sendFile($dir)->send();
                    } else {
                        Yii::$app->response->statusCode = 404;
                        return  [
                            'message' => 'Not found'
                        ];
                    }
                } else {
                    Yii::$app->response->statusCode = 401;
                    return  [
                        'message' => 'Forbidden for you'
                    ];
                }
            } else {
                Yii::$app->response->statusCode = 404;
                return  [
                    'message' => 'Not found'
                ];
            }
        } else {
            Yii::$app->response->statusCode = 403;
            return    [
                'message' => 'Login failed'
            ];
        }
    }

    public function actionDeleteFile($file_id = null)
    {
        $result = [];
        $identity = yii::$app->user->identity;
        if ($identity) {
            $file = File::findOne(['file_id' => $file_id]);
            if ($file) {
                $author = Authors::findOne(['file_id' => $file->id, 'user_id' => $identity->id]);
                if ($author) {
                    $dir = Yii::getAlias('@app/uploads/' . $file->file_id . '.' . $file->extension);
                    if (file_exists($dir)) {
                        unlink($dir);
                        $file->delete();
                        Yii::$app->response->statusCode = 200;
                        $result = [
                            'message' => 'file deleted'
                        ];
                    } else {
                        Yii::$app->response->statusCode = 404;
                        $result = [
                            'message' => 'Not found'
                        ];
                    }
                } else {
                    Yii::$app->response->statusCode = 401;
                    $result =  [
                        'message' => 'Forbidden for you'
                    ];
                }
            } else {
                Yii::$app->response->statusCode = 404;
                $result =  [
                    'message' => 'Not found'
                ];
            }
        } else {
            Yii::$app->response->statusCode = 403;
            $result =    [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }

    public function actionRenameFile($file_id = null)
    {
        $data = yii::$app->request->post();
        $result = [];
        $identity = yii::$app->user->identity;
        if ($identity) {
            $file = File::findOne(['file_id' => $file_id]);
            if ($file) {
                $author = Authors::findOne(['file_id' => $file->id, 'user_id' => $identity->id, 'role_id' => 1]);
                if ($author) {
                    $name = $data['name'];
                    $model = new File();
                    $model->load($data, '');
                    if ($model->validate()) {


                        if (!$file->isUniqueName($name, $identity->id)) {
                            $i = 1;
                            while (!$file->isUniqueName($name, $identity->id)) {
                                $name = $file->name . "($i)." . $file->extension;
                                $i++;
                            }
                        }
                        $file->name = $name;

                        $file->save(false);
                        Yii::$app->response->statusCode = 200;
                        $result = [
                            'success' => true,
                            'message' => 'Renamed',
                            'code' => 200
                        ];
                    } else {
                        yii::$app->response->statusCode = 401;
                        $result[] = [
                            'success' => false,
                            'message' => $model->errors,
                            'name' => $model->name,
                        ];
                    }
                } else {
                    Yii::$app->response->statusCode = 401;
                    $result =  [
                        'message' => 'Forbidden for you'
                    ];
                }
            } else {
                Yii::$app->response->statusCode = 404;
                $result =  [
                    'message' => 'Not found'
                ];
            }
        } else {
            Yii::$app->response->statusCode = 403;
            $result =    [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }


    public function actionAddCo($file_id = null)
    {

        $data = yii::$app->request->post();
        $result = [];
        $identity = yii::$app->user->identity;
        if ($identity) {
            $file = File::findOne(['file_id' => $file_id]);
            if ($file) {
                $author = Authors::findOne(['file_id' => $file->id, 'user_id' => $identity->id, 'role_id' => 1]);
                if ($author) {
                    $user = User::findOne(['email' => $data['email']]);
                    if ($user) {
                        $coauthor  = new Authors();
                        $coauthor->role_id = 2;
                        $coauthor->user_id = $user->id;
                        $coauthor->file_id = $file->id;
                        if ($coauthor->validate()) {

                            $coauthor->save();
                            $coathors = Authors::find()
                                ->select(
                                    [
                                        'first_name',
                                        'last_name',
                                        'email',
                                        'role.title',
                                    ]
                                )
                                ->innerJoin('user', 'user.id = authors.user_id')
                                ->innerJoin('file', 'file.id = authors.file_id')
                                ->innerJoin('role', 'role.id = authors.role_id')
                                ->where(['file.file_id' => $file->file_id])
                                ->asArray()
                                ->all();
                            // var_dump($coathors);
                            // die;


                            Yii::$app->response->statusCode = 200;
                            foreach ($coathors as $coath) {
                                $result['access'][] =
                                    [
                                        'full_name' => $coath['first_name'] . $coath['last_name'],
                                        'email' => $coath['email'],
                                        'type' => $coath['title'],
                                        'code' => 200,
                                    ];
                            }
                        } else {
                            yii::$app->response->statusCode = 401;
                            $result[] = [
                                'success' => false,
                                'message' => $coauthor->errors,
                            ];
                        }
                    } else {
                        Yii::$app->response->statusCode = 404;
                        $result =  [
                            'message' => 'User Not found'
                        ];
                    }
                } else {
                    Yii::$app->response->statusCode = 401;
                    $result =  [
                        'message' => 'Forbidden for you'
                    ];
                }
            } else {
                Yii::$app->response->statusCode = 404;
                $result =  [
                    'message' => 'Not found'
                ];
            }
        } else {
            Yii::$app->response->statusCode = 403;
            $result =    [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }

    public function actionDeleteCo($file_id)
    {

        $data = yii::$app->request->post();
        $result = [];
        $identity = yii::$app->user->identity;
        if ($identity) {
            $file = File::findOne(['file_id' => $file_id]);
            if ($file) {
                $author = Authors::findOne(['file_id' => $file->id, 'user_id' => $identity->id, 'role_id' => 1]);
                if ($author) {
                    $user = User::findOne(['email' => $data['email']]);
                    if ($user) {
                        $coauthor  = Authors::findOne(['file_id' => $file->id, 'user_id' => $user->id,]);
                        if ($coauthor) {
                            // var_dump('dsads');die;
                            $coauthor->delete();
                        }

                        $coathors = Authors::find()
                            ->select(
                                [
                                    'first_name',
                                    'last_name',
                                    'email',
                                    'role.title',
                                ]
                            )
                            ->innerJoin('user', 'user.id = authors.user_id')
                            ->innerJoin('file', 'file.id = authors.file_id')
                            ->innerJoin('role', 'role.id = authors.role_id')
                            ->where(['file.file_id' => $file->file_id])
                            ->asArray()
                            ->all();
                        // var_dump($coathors);
                        // die;


                        Yii::$app->response->statusCode = 200;
                        foreach ($coathors as $coath) {
                            $result['access'][] =
                                [
                                    'full_name' => $coath['first_name'] . $coath['last_name'],
                                    'email' => $coath['email'],
                                    'type' => $coath['title'],
                                    'code' => 200,
                                ];
                        }
                    } else {
                        Yii::$app->response->statusCode = 404;
                        $result =  [
                            'message' => 'User Not found'
                        ];
                    }
                } else {
                    Yii::$app->response->statusCode = 401;
                    $result =  [
                        'message' => 'Forbidden for you'
                    ];
                }
            } else {
                Yii::$app->response->statusCode = 404;
                $result =  [
                    'message' => 'Not found'
                ];
            }
        } else {
            Yii::$app->response->statusCode = 403;
            $result =    [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }

    public function actionCheckAll()
    {

        $identity = yii::$app->user->identity;
        if ($identity) {
            $files = File::find()
                ->select(
                    [
                        'url',
                        'name',
                        'file.file_id',
                        'file.id',
                    ]
                )
                ->innerJoin('authors', 'authors.file_id = file.id')
                ->where(['authors.user_id' => $identity->id, 'role_id' => 1])
                ->all();
            foreach ($files as $file) {
                $authors = Authors::find()
                    ->select([
                        'first_name',
                        'last_name',
                        'email',
                        'title',
                    ])
                    ->innerJoin('user', 'user.id = authors.user_id')
                    ->innerJoin('role', 'role.id = authors.role_id')
                    ->where(['authors.file_id' => $file->id])
                    ->asArray()
                    ->all();
                $arr = [];
                foreach ($authors as $author) {
                    $arr[] = [
                        'fullName' => $author['first_name'] . ' ' . $author['last_name'],
                        'role' => $author['title'],
                        'email' => $author['email'],
                    ];
                }
                $result[] = [
                    'url' => $file->url,
                    'code' => 200,
                    'name' => $file->name,
                    'file_id' => $file->file_id,
                    'access' => $arr

                ];
            }
            Yii::$app->response->statusCode = 200;
        } else {
            Yii::$app->response->statusCode = 403;
            $result =    [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }

    public function actionCheckAccess()
    {

        $identity = yii::$app->user->identity;
        if ($identity) {
            $files = File::find()
                ->select(
                    [
                        'url',
                        'name',
                        'file.file_id',
                        'file.id',
                    ]
                )
                ->innerJoin('authors', 'authors.file_id = file.id')
                ->where(['authors.user_id' => $identity->id])
                ->all();
            foreach ($files as $file) {
                $result[] = [
                    'url' => $file->url,
                    'code' => 200,
                    'name' => $file->name,
                    'file_id' => $file->file_id,
                ];
            }
            Yii::$app->response->statusCode = 200;
        } else {
            Yii::$app->response->statusCode = 403;
            $result =    [
                'message' => 'Login failed'
            ];
        }
        return $result;
    }
}
