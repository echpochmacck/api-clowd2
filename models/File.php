<?php

namespace app\models;

use Yii;

use yii\web\IdentityInterface;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\behaviors\TimestampBehavior;
/**
 * This is the model class for table "file".
 *
 * @property int $id
 * @property string $file_id
 * @property string $name
 * @property string $url
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @property Authors[] $authors
 */
class File extends \yii\db\ActiveRecord
{
    public $file;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // [['file_id', 'name', 'url', 'created_at'], 'required']
            [['file_id'],'unique'],
            [['created_at', 'updated_at'], 'safe'],
            [['file_id', 'name', 'url', 'extension'], 'string', 'max' => 255],
            [['name'], 'required'],
            ['file', 'file', 'extensions' => ['doc', 'pdf', 'docx', 'zip', 'jpeg', 'jpg', 'png', 'txt'], 'maxSize' => 2 * 1024 * 1024]
        ];
    }

    public function isUniqueName($name, $user_id)
    {
        $name =  static::find()
            ->select([
                'name'
            ])
            ->innerJoin('authors', 'authors.file_id = file.id')
            ->where(['authors.user_id' => $user_id, 'name' => $name, 'authors.role_id' => 1])
            ->asArray()
            ->all();
                // var_dump($name);die;
        if ($name)
            return false;
        else
            return true;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'file_id' => 'File ID',
            'name' => 'Имя файла',
            'url' => 'ссылка',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Authors]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAuthors()
    {
        return $this->hasMany(Authors::class, ['file_id' => 'id']);
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],

                ],
                'value' => new Expression('NOW()'),
            ],
        ];
    }
}
