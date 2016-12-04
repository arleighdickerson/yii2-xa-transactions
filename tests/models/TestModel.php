<?php


namespace models;


use yii\db\ActiveRecord;

/**
 * Class TestModel
 * @package models
 * @property int $id
 * @property string $value
 */
class TestModel extends ActiveRecord {
    public static function tableName() {
        return 'test';
    }

    public static function classes() {
        $classes = [
            self::class,
            TestModel::class
        ];
        return array_combine($classes, $classes);
    }
}