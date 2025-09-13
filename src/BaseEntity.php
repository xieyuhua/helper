<?php
/*
 * @Descripttion:
 */

namespace app\entity;

use PDO;
use think\Entity;
use think\facade\Cache;
use think\facade\Db;
use think\Request;
use think\Validate;

/**
 * Entity基类
 */
class BaseEntity extends Entity
{
    // 查询对象
    protected static $query_obj = null;
    // 当前用户id
    protected $user_id = null;
    // 当前用户名称
    protected $username = null;

    //db字
    protected $connection = 'mysql';
    //表名字
    protected $table = '';
    //预加载
    protected $withs = [];
    //验证规则
    protected $rule = [];
    //验证信息
    protected $message = [];
    //验证场景
    protected $scene = [];
    //错误信息
    protected $error;
    //是否缓存
    protected $is_cache = 0;
    //输出sql
    protected $is_sql = 0;

    public function __construct($entity = null, $connection = null)
    {
        //初始化entity
        if (!empty($entity)) {
            parent::__construct(null);
        }

        // 指定db
        if (!empty($connection)) {
            $this->connection = $connection;
        }

        //获取类名
        $this->table = $this->getTable();

        //获取登录信息
        $this->username = app()->request->username ?? '';
        $this->user_id = app()->request->user_id ?? null;

        $this->is_cache = $this->isCache();
    }

    /**
     * 获取表名
     *
     * @return string
     */
    public function getTable(): string
    {
        // 反射机制
        $reflection = new \ReflectionClass(static::class);
        // \print_r($reflection);exit;
        // 尝试获取子类定义的 table 属性
        if ($reflection->hasProperty('table')) {
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            // 获取子类属性默认值（不使用实例）
            $defaultProperties = $reflection->getDefaultProperties();
            $tableValue = $defaultProperties['table'] ?? null;
            if (!empty($tableValue)) {
                return $tableValue; // 返回子类定义的值
            }
        }
        //驼峰转下划线,类名就是表名
        $parts = explode('\\', static::class);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', end($parts)));
    }

    /**
     * 复用还是新建对象
     *
     * @obj
     */
    final public function table(string $table)
    {
        if (empty($table)) {
            $table = $this->getTable();
        }
        // $this->table = $table;
        // return $this;
        $obj = new static();
        $obj->table = $table;
        return $obj;
    }

    /**
     * 预加载表 一对多 in 、 一对一 join
     *
     * 表名 预加载表 主键， 表主键，预加载字段，预加载条件
     *
     * ['order_goods', 'order_goods.order_id', 'order.id', 'order_goods.*', [] ],
     * @param string $with
     * @return $this
     */
    final public function with(array $with)
    {
        //[['role', 'id,role_id']]
        $this->withs = $with;
        return $this;
    }

    /**
     * pageList 、getInfo、getList 输出sql
     *
     * @obj this
     */
    public function sql(int $is_sql)
    {
        $this->is_sql = $is_sql;
        return $this;
    }

    /**
     * 执行存储过程
     */
    final public function procedure($sql)
    {
        // 获取PDO连接
        // $pdo = Db::getPDO();
        $stmt = Db::connect($this->connection)->getPDOStatement($sql, [], true, true);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // print_r($result);exit;
        return $result;
    }

    /**
     * 是否缓存
     *
     * @return boolean
     */
    public function isCache()
    {
        return 0;
    }

    /**
     * 获取列表数据
     * @param array $condition
     * @param string $field
     * @param string $order
     * @param number $page
     * @param array $join
     * @param string $group
     * @param string $limit
     * @param string $data
     * @return mixed
     */
    final public function getList($condition = [], $field = true, $order = '', $alias = 'a', $join = [], $group = '', $limit = null)
    {
        if ($this->is_cache && empty($join)) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        self::$query_obj = Db::connect($this->connection)->name($this->table)->where($condition)->order($order);

        if (!empty($join)) {
            self::$query_obj->alias($alias);
            self::$query_obj = $this->parseJoin(self::$query_obj, $join);
        }

        if (!empty($group)) {
            self::$query_obj = self::$query_obj->group($group);
        }

        if (!empty($limit)) {
            self::$query_obj = self::$query_obj->limit($limit);
        }

        if ($this->is_sql) {
            exit(self::$query_obj->field($field)->fetchSql($this->is_sql)->select());
        }

        $result = self::$query_obj->field($field)->select()->toArray();

        // 处理预加载数据
        if (is_array($this->withs) && !empty($this->withs)) {
            $this->withSelect($result);
        }

        self::$query_obj->removeOption();

        if ($this->is_cache && empty($join)) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * 获取数据
     */
    final public function all()
    {
        if ($this->is_cache) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_';
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }
        $result = Db::connect($this->connection)->name($this->table)->select()->toArray();

        // 处理预加载数据
        if (is_array($this->withs) && !empty($this->withs)) {
            $this->withSelect($result);
        }

        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * 获取分页列表数据
     * @param unknown $where
     * @param string $field
     * @param string $order
     * @param number $page
     * @param string $list_rows
     * @param string $alias
     * @param unknown $join
     * @param string $group
     * @param string $limit
     */
    final public function pageList($condition = [], $field = true, $order = '', $page = 1, $list_rows = 10, $alias = 'a', $join = [], $group = null, $limit = null)
    {
        //关联查询多表无法控制不缓存,单独业务处理
        if ($this->is_cache && empty($join)) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }
        self::$query_obj = Db::connect($this->connection)->name($this->table)->alias($alias)->where($condition)->order($order);
        $count_obj = Db::connect($this->connection)->name($this->table)->alias($alias)->where($condition)->order($order);
        if (!empty($join)) {
            $db_obj = self::$query_obj;
            self::$query_obj = $this->parseJoin($db_obj, $join);
            $count_obj = $this->parseJoin($count_obj, $join);
        }

        if (!empty($group)) {
            self::$query_obj = self::$query_obj->group($group);
            $count_obj = $count_obj->group($group);
        }

        if (!empty($limit)) {
            self::$query_obj = self::$query_obj->limit($limit);
        }

        $count = $count_obj->count();

        if ($this->is_sql) {
            if ($list_rows == 0) {
                //查询全部
                exit(self::$query_obj->field($field)->limit($count)->page($page)->fetchSql($this->is_sql)->select());
            } else {
                exit(self::$query_obj->field($field)->limit($list_rows)->page($page)->fetchSql($this->is_sql)->select());
            }
        }

        if ($list_rows == 0) {
            //查询全部
            $result_data = self::$query_obj->field($field)->limit($count)->page($page)->select()->toArray();
            $result['page_count'] = 1;
        } else {
            $result_data = self::$query_obj->field($field)->limit($list_rows)->page($page)->select()->toArray();
            $result['page_count'] = ceil($count / $list_rows);
        }

        // 处理预加载数据
        if (is_array($this->withs) && !empty($this->withs)) {
            $this->withSelect($result_data);
        }

        $result['count'] = $count;
        $result['list'] = $result_data;

        self::$query_obj->removeOption();
        if ($this->is_cache && empty($join)) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * 预加载数据处理
     *
     * @param [type] $data
     * @return void
     */
    final public function withSelect(&$data)
    {
        // ['order_goods', 'goods_list', 'order_id', 'id', '*', [['status', '=', 1]] ],
        $withData = [];
        foreach ($this->withs as $wvalue) {
            // 初始值
            [$table_name, $alias, $pk_id, $id, $field, $where] = [
                $wvalue[0] ?? 'table_name',
                $wvalue[1] ?? '',
                $wvalue[2] ?? 'pk_id',
                $wvalue[3] ?? 'id',
                $wvalue[4] ?? '*',
                $wvalue[5] ?? [],
            ];
            if (empty($where) || !is_array($where)) {
                $where = [];
            }
            if (isset($where[0]) && !is_array($where[0])) {
                $where = [$where];
            }
            //
            $ids_arr = array_columnxh($data, $id);
            //不存在别名，取表名
            if (empty($alias)) {
                $alias = $table_name;
            }
            //
            $where[] = [$pk_id, 'in', $ids_arr];

            //一对多，还是多对一
            if ($field != '*' && count(explodexh(',', $field)) == 1) {
                $withData[$alias . ':' . $id] = $this->table($table_name)->getColumn($where, $field, $pk_id);
            } else {
                $getList = $this->table($table_name)->getList($where, $field);
                foreach ($getList as $item) {
                    $withData[$alias . ':' . $id][$item[$pk_id]][] = $item;
                }
            }
        }

        // 处理数据
        foreach ($data as &$list) {
            // 加载预加载
            foreach ($withData as $kk => $val) {
                [$k, $id] = explode(':', $kk);
                $list[$k] = $val[$list[$id]] ?? '';
            }
        }
    }

    /**
     * 获取单条数据
     * @param array $where
     * @param string $field
     * @param string $join
     * @param string $data
     * @return mixed
     */
    final public function getInfo($where = [], $field = true, $alias = 'a', $join = null, $data = null)
    {
        //关联查询多表无法控制不缓存,单独业务处理
        if ($this->is_cache && empty($join)) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        if (empty($join)) {
            $result = Db::connect($this->connection)->name($this->table)->where($where)->field($field)->find($data);
        } else {
            $db_obj = Db::connect($this->connection)->name($this->table)->alias($alias);
            $db_obj = $this->parseJoin($db_obj, $join);

            if ($this->is_sql) {
                exit($db_obj->where($where)->field($field)->fetchSql($this->is_sql)->find($data));
            }

            $result = $db_obj->where($where)->field($field)->find($data);
        }

        // 处理预加载数据
        if (is_array($this->withs) && !empty($this->withs)) {
            $this->withSelect($result);
        }

        if ($this->is_cache && empty($join)) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * join分析
     * @access protected
     * @param array $join
     * @param array $options 查询条件
     * @return this
     */
    protected function parseJoin($db_obj, $join)
    {
        foreach ($join as $item) {
            list($table, $on, $type) = $item;
            $type = strtolower($type);
            switch ($type) {
                case "left":
                    $db_obj = $db_obj->leftJoin($table, $on);
                    break;
                case "inner":
                    $db_obj = $db_obj->join($table, $on);
                    break;
                case "right":
                    $db_obj = $db_obj->rightjoin($table, $on);
                    break;
                case "full":
                    $db_obj = $db_obj->fulljoin($table, $on);
                    break;
                default:
                    break;
            }
        }
        return $db_obj;
    }

    /**
     * /**
     * 获取某个列的数组
     * @param array $where 条件
     * @param string $field 字段名 多个字段用逗号分隔
     * @param string $key 索引
     * @return array
     */
    final public function getColumn($where = [], $field = '', $key = '')
    {
        if ($this->is_cache) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        $result = Db::connect($this->connection)->name($this->table)->where($where)->column($field, $key);
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param array $where 条件
     * @param string $field 字段名
     * @param mixed $default 默认值
     * @param bool $force 强制转为数字类型
     * @return mixed
     */
    final public function getValue($where = [], $field = '', $default = null, $force = false)
    {
        if ($this->is_cache) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        $result = Db::connect($this->connection)->name($this->table)->where($where)->value($field, $default, $force);
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * 新增数据
     * @param array $data 数据
     * @param boolean $is_return_pk 返回自增主键
     */
    final public function add($data = [], $is_return_pk = true)
    {
        $filed = Db::connect($this->connection)->getTableFields($this->table);
        $filed = array_flip($filed);
        if (is_array($filed)) {
            // 取两个数组的键名交集
            $data = array_intersect_key($data, $filed);
        }
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }
        return Db::connect($this->connection)->name($this->table)->insert($data, true, $is_return_pk);
    }

    /**
     * 新增多条数据
     * @param array $data 数据
     * @param int $limit 限制插入行数
     */
    final public function addList($data = [], $limit = null)
    {
        if (empty($data)) {
            return true;
        }
        $inser_data = [];
        $filed = Db::connect($this->connection)->getTableFields($this->table);
        $filed = array_flip($filed);
        if (is_array($filed)) {
            // 取两个数组的键名交集
            foreach ($data as $v) {
                $inser_data[] = array_intersect_key($v, $filed);
            }
        } else {
            $inser_data = $data;
        }
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }
        return Db::connect($this->connection)->name($this->table)->insertAll($inser_data, false, $limit);
    }

    /**
     * 更新数据
     * @param array $where 条件
     * @param array $data 数据
     */
    final public function update($data = [], $where = [], $array_diff = [])
    {
        $fileds = Db::connect($this->connection)->getTableFields($this->table);
        $fileds = array_flip($fileds);
        if (is_array($fileds)) {
            // 取两个数组的键名交集
            $data = array_intersect_key($data, $fileds);
        }

        //去除没有修改的数据
        if (!empty($array_diff)) {
            $data = $this->array_diff_assocxh($data, $array_diff);
        }

        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }

        if (empty($data)) {
            return 1;
        }
        return Db::connect($this->connection)->name($this->table)->where($where)->update($data);
    }

    /**
     * 设置某个字段值
     * @param array $where 条件
     * @param string $field 字段
     * @param string $value 值
     */
    final public function setFieldValue($where = [], $field = '', $value = '')
    {
        return Db::connect($this->connection)->name($this->table)->update([$field => $value], $where);
    }

    /**
     * 设置数据列表
     * @param array $data_list 数据
     * @param boolean $replace 是否自动识别更新和写入
     */
    final public function setList($data_list = [], $replace = false)
    {
        $inser_data = [];
        $filed = Db::connect($this->connection)->getTableFields($this->table);
        $filed = array_flip($filed);
        if (is_array($filed)) {
            // 取两个数组的键名交集
            foreach ($data_list as $v) {
                $inser_data[] = array_intersect_key($v, $filed);
            }
        } else {
            $inser_data = $data_list;
        }
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }
        return Db::connect($this->connection)->name($this->table)->saveAll($inser_data, $replace);
    }

    /**
     * 删除数据
     * @param array $where 条件
     */
    final public function delete($where = [])
    {
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }
        return Db::connect($this->connection)->name($this->table)->where($where)->delete();
    }

    /**
     * 统计数据
     * @param array $where 条件
     * @param string $type 查询类型  count:统计数量|max:获取最大值|min:获取最小值|avg:获取平均值|sum:获取总和
     */
    final public function stat($where = [], $type = 'count', $field = 'id')
    {
        if ($this->is_cache) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }
        $result = Db::connect($this->connection)->name($this->table)->where($where)->$type($field);
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }
        return $result;
    }

    /**
     * SQL查询
     * @param string $sql
     * @return mixed
     */
    final public function query($sql = '')
    {
        return Db::connect($this->connection)->query($sql);
    }

    /**
     * 返回总数
     * @param unknown $where
     */
    final public function getCount($where = [], $field = '*', $alias = 'a', $join = null, $group = null)
    {
        if ($this->is_cache && empty($join)) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }
        if (empty($join)) {
            if (empty($group)) {
                $result = Db::connect($this->connection)->name($this->table)->where($where)->count($field);
            } else {
                $result = Db::connect($this->connection)->name($this->table)->group($group)->where($where)->count($field);
            }
        } else {
            $db_obj = Db::connect($this->connection)->name($this->table)->alias($alias);
            $db_obj = $this->parseJoin($db_obj, $join);
            if (empty($group)) {
                $result = $db_obj->where($where)->count($field);
            } else {
                $result = $db_obj->group($group)->where($where)->count($field);
            }
        }
        if ($this->is_cache && empty($join)) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * 返回总数
     * @param unknown $where
     */
    final public function getSum($where = [], $field = '', $alias = 'a', $join = null)
    {
        if ($this->is_cache && empty($join)) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        if (empty($join)) {
            $result = Db::connect($this->connection)->name($this->table)->where($where)->sum($field);
        } else {
            $db_obj = Db::connect($this->connection)->name($this->table)->alias($alias);
            $db_obj = $this->parseJoin($db_obj, $join);
            $result = $db_obj->where($where)->sum($field);
        }
        if ($this->is_cache && empty($join)) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $result);
        }

        return $result;
    }

    /**
     * SQL执行
     */
    final public function execute($sql = '')
    {
        return Db::connect($this->connection)->execute($sql);
    }

    /**
     * 查询第一条数据
     * @param array $condition
     */
    final public function getFirstData($condition, $field = '*', $order = "")
    {
        if ($this->is_cache) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        $data = Db::connect($this->connection)->name($this->table)->where($condition)->order($order)->field($field)->find();
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $data);
        }

        return $data;
    }

    /**
     * 查询第一条数据
     * @param array $condition
     */
    final public function getFirstDataView($condition, $field = '*', $order = "", $alias = 'a', $join = [], $group = null)
    {
        if ($this->is_cache && empty($join)) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }

        self::$query_obj = Db::connect($this->connection)->name($this->table)->alias($alias)->where($condition)->order($order)->field($field);
        if (!empty($join)) {
            $db_obj = self::$query_obj;
            self::$query_obj = $this->parseJoin($db_obj, $join);
        }

        if (!empty($group)) {
            self::$query_obj = self::$query_obj->group($group);
        }
        $data = self::$query_obj->find();
        if ($this->is_cache && empty($join)) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $data);
        }

        return $data;
    }

    /**
     * 验证
     * @param array $data
     * @param string $scene_name
     * @return array[$code, $error]
     */
    public function fieldValidate($data, $scene_name = '')
    {
        $validate = new Validate($this->rule, $this->message);

        if (is_array($scene_name)) {
            $validate_result = $validate->rule($scene_name)->batch(true)->check($data);
        } else {
            $validate->scene($this->scene);
            $validate_result = $validate->scene($scene_name)->batch(false)->check($data);
        }

        return $validate_result ? [true, ''] : [false, $validate->getError()];
    }

    /**
     * 事物开启
     */
    final public function startTrans()
    {

        return Db::connect($this->connection)->startTrans();
    }

    /**
     * 事物提交
     */
    final public function commit()
    {

        return Db::connect($this->connection)->commit();
    }

    /**
     * 事物回滚
     */
    final public function rollback()
    {
        if ($this->is_cache) {
            Cache::clear();
        }
        return Db::connect($this->connection)->rollback();
    }

    /**
     * 获取错误信息
     */
    final public function getError()
    {
        return $this->error;
    }

    /**
     * 自增数据
     * @param array $where
     * @param $field
     * @param int $num
     * @return int
     */
    final public function setInc($where, $field, $num = 1)
    {
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }
        return Db::connect($this->connection)->name($this->table)->where($where)->inc($field, $num)->update();
    }

    /**
     * 自减数据
     * @param $where
     * @param $field
     * @param int $num
     * @return int
     */
    final public function setDec($where, $field, $num = 1)
    {
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->clear();
        }
        return Db::connect($this->connection)->name($this->table)->where($where)->dec($field, $num)->update();
    }

    /**
     * 获取最大值
     * @param array $where
     * @param $field
     * @return mixed
     */
    final public function getMax($where, $field)
    {
        if ($this->is_cache) {
            $cache_name = $this->table . '_' . __FUNCTION__ . '_' . serialize(func_get_args());
            $cache = Cache::get($cache_name);
            if (!empty($cache)) {
                return $cache;
            }
        }
        $data = Db::connect($this->connection)->name($this->table)->where($where)->max($field);
        if ($this->is_cache) {
            Cache::tag("cache_table" . $this->table)->set($cache_name, $data);
        }

        return $data;
    }

    /**
     * 获取分页列表数据 只是单纯的实现部分功能 其他使用还是用pageList吧
     * @param unknown $where
     * @param string $field
     * @param string $order
     * @param number $page
     * @param string $list_rows
     * @param string $alias
     * @param unknown $join
     * @param string $group
     * @param string $limit
     */
    final public function Lists($condition = [], $field = true, $order = '', $page = 1, $list_rows = 10, $alias = 'a', $join = [], $group = null, $limit = null)
    {
        self::$query_obj = Db::connect($this->connection)->name($this->table)->alias($alias)->where($condition);
        $count_obj = Db::connect($this->connection)->name($this->table)->alias($alias)->where($condition);
        if (!empty($join)) {
            $db_obj = self::$query_obj;
            self::$query_obj = $this->parseJoin($db_obj, $join);
            $count_obj = $this->parseJoin($count_obj, $join);
        }

        if (!empty($group)) {
            self::$query_obj = self::$query_obj->group($group);
            $count_obj = $count_obj->group($group);
        }

        if (!empty($limit)) {
            self::$query_obj = self::$query_obj->limit($limit);
        }

        $count = $count_obj->count();
        if ($list_rows == 0) {
            //查询全部
            $result_data = self::$query_obj->field($field)->order($order)->limit($count)->page($page)->select()->toArray();
            $result['page_count'] = 1;
        } else {
            $result_data = self::$query_obj->field($field)->order($order)->limit($list_rows)->page($page)->select()->toArray();
            $result['page_count'] = ceil($count / $list_rows);
        }

        // 处理预加载数据
        if (is_array($this->withs) && !empty($this->withs)) {
            $this->withSelect($result_data);
        }

        $result['count'] = $count;
        $result['list'] = $result_data;

        self::$query_obj->removeOption();
        return $result;
    }

    /**
     * 不读取缓存--获取单条数据
     * @param array $where
     * @param string $field
     * @param string $join
     * @param string $data
     * @return mixed
     */
    final public function getInfoTo($where = [], $field = true, $alias = 'a', $join = null, $data = null)
    {
        if (empty($join)) {
            $result = Db::connect($this->connection)->name($this->table)->where($where)->field($field)->find($data);
        } else {
            $db_obj = Db::connect($this->connection)->name($this->table)->alias($alias);
            $db_obj = $this->parseJoin($db_obj, $join);
            $result = $db_obj->where($where)->field($field)->find($data);
        }

        return $result;
    }

    /**
     * 设置缓存
     *
     * @param integer $is_cache
     * @return void
     */
    public function setIsCache($is_cache = 1)
    {
        $this->is_cache = $is_cache;
        return $this;
    }

    /**
     * 匹配整数或浮点数
     *
     * @param string $str
     * @return boolean
     */
    protected function isNumericString($str)
    {
        // 匹配整数或浮点数（含正负号）
        return preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $str) && is_numeric($str);
    }

    /**
     *  字符串和数字 =》  字符串
     *
     * 1.00,'1','1.00','01','1.0' 转字符串 '1'
     *
     * '0.00', 0, '0.0' 转字符串 '0'
     *
     * null,'null','' 转 null
     *
     * 'true','false',false,true 转 bool
     *
     *  float int string
     */
    protected function array_vstr($arr)
    {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = $this->array_vstr($value);
            } else {
                if ($value === 0) {
                    $arr[$key] = '0';
                    continue;
                }

                // 非 整数、浮点数
                if ($value == '' || $value == null || $value == 'null' || preg_match('/[\x{4e00}-\x{9fa5}]/u', $value)) {
                    $arr[$key] = $value;

                    // null 和 null 字符串
                    if ($value == null || is_string($value) && strtolower($value) == 'null') {
                        $arr[$key] = null;
                    }
                } else {
                    // 匹配整数  或  浮点数
                    if ($this->isNumericString($value) || $value == '0') {
                        $arr[$key] = sprintf('%.11g', $value);
                    } else {
                        $arr[$key] = $value;
                    }
                }
            }
        }
        return $arr;
    }

    /**
     *  (关联数组) 前面一个数组减去后面一个数组，去除相同部分，保留前一个数组
     *  array_diff[1,2,3], [3,2,5]）
     */
    protected function array_diff_assocxh($arr1, $arr2)
    {
        $arrValue = [];
        if (is_array($arr1) && is_array($arr2)) {
            $arrValue = array_diff_assoc($this->array_vstr($arr1), $this->array_vstr($arr2));
        } else {
            if (is_array($arr1) && !is_array($arr2)) {
                $arrValue = $arr1;
            }
        }
        return $arrValue;
    }

}
