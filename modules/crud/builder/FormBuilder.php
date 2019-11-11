<?php
namespace app\modules\crud\builder;

use Yii;
use yii\db\ActiveRecord;
use yii\base\InvalidConfigException;

use app\modules\crud\builder\Base;

/**
 * XXX
 *
 */
class FormBuilder extends Base {
    public $formExtraControls = ['save', 'cancel'];

    public $fieldSet2fields;
    public $fieldSetLegends = [];

    protected $_extraControlVar = 'form';

    public function controller2this($controller) {
        if (isset($controller->modelClass)) {
            $this->static2this($controller->modelClass, 'fb_');
        }

        $this->object2this($controller);
    }

    public function build(ActiveRecord $model) {
        $this->_isExtraControlCreated = false;
        foreach (['fieldTypes', 'type2fields', 'fieldOptions', 'enumFields'] as $param) {
            if (null === $this->{$param}) {
                $this->{$param} = [];
            }
        }

        foreach ($this->type2fields as $type => $fields) {
            if (is_string($fields)) {
                $fields = preg_split('/\s+/', preg_replace('/^\s+|\s+$/', '', $fields));
            }

            foreach ($fields as $field) {
                $this->fieldTypes[$field] = $type;
            }
        }

        $this->modelClass = get_class($model);

        $this->beforeBuild();
        $this->initNameAttr();

        $allows = $model->activeAttributes();
        foreach ($this->fieldTypes as $attr => $type) {
            if ('static' == $type || 'staticControl' == $type) {
                $allows[] = $attr;
            }
        }

        if (null === $this->fields) {
            $attrs = array_intersect($model->attributes(), $allows);
            $this->fields = array_diff($attrs, $this->modelClass::primaryKey());
        } else {
            $notRule = array_diff($this->fields, $allows);
            if ($notRule) {
                $notRule = implode("', '", $notRule);
                throw new InvalidConfigException("No exist rules on '{$notRule}' fields");
            }
        }

        foreach ($this->fields as $field) {
            $type = $this->getType($field, $model);
            if (!$type) {
                continue;
            }

            $this->fieldTypes[$field] = $type;
            if (in_array($type, $this->enumFieldTypes) || in_array($field, $this->enumFields)) {
                $this->initEnumOptions($model, $field);
            }
        }

        $this->createExtraControls();

        $this->afterBuild();

        $this->extraControlsToPlace();
    }

    protected function initEnumOptions($model, $attr) {
        if (isset($this->enumOptions[$attr])) {
            $options = $this->enumOptions[$attr];
            if (is_string($options)) {
                $options = [$model, $options];
            }

            if (is_callable($options)) {
                $this->enumOptions[$attr] = call_user_func($options);
            }

            return;
        }

        $this->initEnumOptionsByValidator($model, $attr);
    }

    protected function getType($attr, $model) {
        if (isset($this->fieldTypes[$attr])) {
            return $this->fieldTypes[$attr];
        }

        if (null !== ($type = $this->getControlTypeByDBColumn($attr))) {
            return $type;
        }

        if (null !== ($type = $this->getControlTypeByValidator($model, $attr))) {
            return $type;
        }

        return $this->uptakeType($attr);
    }

    protected function uptakeType($attr) {
        if (!$this->uptake) {
            return;
        }

        if (in_array($attr, $this->phoneAttrs)) {
            return 'phone';
        }

        if (in_array($attr, $this->emailAttrs)) {
            return 'email';
        }
    }

    public function getFieldSetLegend($fieldSet) {
        if (isset($this->fieldSetLegends[$fieldSet])) {
            return $this->fieldSetLegends[$fieldSet];
        }

        return Yii::t($this->messageCategory, $fieldSet);
    }
}