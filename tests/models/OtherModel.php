<?php


namespace models;


use yii\db\ActiveRecord;
use Yii;

/**
 * Class OtherModel
 * @package models
 * @property int $id
 * @property string $value
 */
class OtherModel extends TestModel {
    public static function getDb() {
        return Yii::$app->get('db2');
    }

    public static function tableName() {
        return 'other';
    }
}
