<?php

namespace library;

use think\Db;
use think\db\Query;

/**
 * 数据工具库类
 *
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/10/21 19:04
 */
class Data {

    /**
     * 删除指定序号
     * @param string $sequence
     * @param string $type
     * @return bool
     */
    static public function deleteSequence($sequence, $type = 'SYSTEM') {
        return Db::table('system_sequence')->where('type', strtoupper($type))->where('sequence', $sequence)->delete();
    }

    /**
     * 生成唯一序号 (失败返回 NULL )
     * @param int $length 序号长度
     * @param string $type 序号顾类型
     * @return string
     */
    static public function createSequence($length = 13, $type = 'SYSTEM') {
        return self::_createSequence($length, strtoupper($type));
    }

    /**
     * 检测并创建序号
     * @param int $length
     * @param string $type
     * @param int $times
     * @param string $sequence
     * @return string
     */
    static protected function _createSequence($length, $type, $times = 0, $sequence = '') {
        if ($times > 10 || $length < 1) {
            return null;
        }
        $i = 0;
        while ($i++ < $length) {
            $sequence .= ($i <= 1 ? rand(1, 9) : rand(0, 9));
        }
        $data = ['sequence' => $sequence, 'type' => $type];
        if (Db::table('system_sequence')->where($data)->count() > 0) {
            return self::_createSequence($length, $type, ++$times);
        }
        if (Db::table('system_sequence')->insert($data) > 0) {
            return $sequence;
        } else {
            return self::_createSequence($length, $type, ++$times);
        }
    }

    /**
     * 数据增量保存
     * @param Query|string $db 数据查询对象
     * @param array $data 需要保存或更新的数据
     * @param string $upkey 条件主键限制
     * @param array $where 其它的where条件
     * @return bool
     */
    static public function save($db, $data, $upkey = 'id', $where = []) {
        $db = is_string($db) ? db($db) : $db;
        $fields = $db->getFieldsType(['table' => $db->getTable()]);
        $_data = [];
        foreach ($data as $k => $v) {
            if (array_key_exists($k, $fields)) {
                $_data[$k] = $v;
            }
        }
        $db = self::_apply_save_where($db, $data, $upkey, $where);
        if ($db->getOptions() && $db->count() > 0) {
            return self::_apply_save_where($db, $data, $upkey, $where)->update($_data) !== FALSE;
        }
        return self::_apply_save_where($db, $data, $upkey, $where)->insert($_data) !== FALSE;
    }

    /**
     * 应用 where 条件
     * @param Query $db 数据查询对象
     * @param array $data 需要保存或更新的数据
     * @param string $upkey 条件主键限制
     * @param array $where 其它的where条件
     * @return Query
     */
    static protected function _apply_save_where(&$db, $data, $upkey, $where) {
        foreach (is_string($upkey) ? explode(',', $upkey) : $upkey as $v) {
            if (is_string($v) && array_key_exists($v, $data)) {
                $db->where($v, $data[$v]);
            } elseif (is_string($v)) {
                $db->where("{$v} IS NULL");
            }
        }
        return $db->where($where);
    }

    /**
     * 更新数据表内容
     * @param Query|string $db
     * @param array $where 额外查询条件
     * @return bool|null
     */
    static public function update(&$db, $where = []) {
        if (!request()->isPost()) {
            return null;
        }
        $db = is_string($db) ? db($db) : $db;
        $ids = explode(',', input("post.id", '', 'trim'));
        $field = input('post.field', '', 'trim');
        $value = input('post.value', '', 'trim');
        $pk = $db->getPk(['table' => $db->getTable()]);
        $db->where(empty($pk) ? 'id' : $pk, 'in', $ids);
        !empty($where) && $db->where($where);
        // 删除模式
        if ($field === 'delete') {
            $fields = $db->getTableFields(['table' => $db->getTable()]);
            if (in_array('is_deleted', $fields)) {
                return false !== $db->update(['is_deleted' => 1]);
            }
            return false !== $db->delete();
        }
        // 更新模式
        return false !== $db->update([$field => $value]);
    }

}
