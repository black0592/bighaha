<?php
/**
 * 所属项目 110.
 * 开发者: 陈一枭
 * 创建日期: 2014-11-18
 * 创建时间: 10:27
 * 版权所有 想天软件工作室(www.ourstu.com)
 */

namespace Common\Model;


use Think\Model;

class ModuleModel extends Model
{

    protected $tableName = 'module';

    public function getAll()
    {

        $module = S('module_all');
        if ($module === false) {
            $dir = $this->getFile(APP_PATH);
            foreach ($dir as $subdir) {
                if (file_exists(APP_PATH . '/' . $subdir . '/Info/info.php') && $subdir != '.' && $subdir != '..') {
                    $info = $this->getModule($subdir);
                    $module[] = $info;

                }
            }

            S('module_all', $module);
        }

        return $module;
    }

    /**
     * 重新通过文件来同步模块
     */
    public function reload()
    {
        $modules = $this->select();
        foreach ($modules as $m) {
            if (file_exists(APP_PATH . '/' . $m['name'] . '/Info/info.php')) {
                $info = array_merge($m, $this->getInfo($m['name']));
                $this->save($info);
            }
        }
        $this->cleanModulesCache();
    }

    public function checkCanVisit($name)
    {
        $modules = $this->getAll();

        foreach ($modules as $m) {
            if (isset($m['is_setup']) && $m['is_setup'] == 0 && $m['name'] == ucfirst($name)) {
                header("Content-Type: text/html; charset=utf-8");
                exit('您所访问的模块未安装，禁止访问。');
            }
        }

    }

    /**检查模块是否已经安装
     * @param $name
     * @return bool
     */
    public function checkInstalled($name)
    {
        $modules = $this->getAll();

        foreach ($modules as $m) {
            if ($m['name'] == $name && $m['is_setup']) {
                return true;
            }
        }
        return false;
    }

    public function  cleanModulesCache()
    {
        $modules = $this->getAll();

        foreach ($modules as $m) {
            $this->cleanModuleCache($m['name']);
        }
        S('module_all', null);
    }

    public function uninstall($id, $withoutData = 1)
    {
        $module = $this->find($id);
        if (!$module || $module['is_setup'] == 0) {
            $this->error = '模块不存在或未安装。';
            return false;
        }
        $this->cleanMenus($module['name']);
        $this->cleanAuthRules($module['name']);
        $this->cleanAction($module['name']);
        $this->cleanActionLimit($module['name']);
        if ($withoutData == 0) {
            //如果不保留数据
            if (file_exists(APP_PATH . '/' . $module['name'] . '/Info/cleanData.sql')) {
                $uninstallSql = APP_PATH . '/' . $module['name'] . '/Info/cleanData.sql';
                $res = D()->executeSqlFile($uninstallSql);
                if ($res === false) {
                    $this->error = '清理模块数据失败，错误信息：' . $res['error_code'];
                    return false;
                }
            }
            //兼容老的卸载方式，执行一边uninstall.sql
            if (file_exists(APP_PATH . '/' . $module['name'] . '/Info/uninstall.sql')) {
                $uninstallSql = APP_PATH . '/' . $module['name'] . '/Info/uninstall.sql';
                $res = D()->executeSqlFile($uninstallSql);
                if ($res === false) {
                    $this->error = '清理模块数据失败，错误信息：' . $res['error_code'];
                    return false;
                }
            }
        }
        $module['is_setup'] = 0;
        $this->save($module);

        $this->cleanModulesCache();
        return true;
    }

    public function install($id)
    {
        $log = '';
        $aId = I('id', 0, 'intval');
        if ($aId != 0) {
            $module = $this->find($id);
        } else {
            $aName = I('get.name', '');
            $module =$this->getModule($aName);
        }
        if ($module['is_setup'] == 1) {
            return array('error_code' => '模块已安装。');
        }
        if (file_exists(APP_PATH . '/' . $module['name'] . '/Info/guide.json')) {
            //如果存在guide.json
            $guide = file_get_contents(APP_PATH . '/' . $module['name'] . '/Info/guide.json');
            $data = json_decode($guide, true);

            //导入菜单项,menu
            $menu = json_decode($data['menu'], true);
            if (!empty($menu)) {
                $this->cleanMenus($module['name']);
                if ($this->addMenus($menu[0]) == true) {
                    $log .= '菜单成功安装;<br/>';
                }
            }

            //导入前台权限,auth_rule
            $auth_rule = json_decode($data['auth_rule'], true);
            if (!empty($auth_rule)) {
                $this->cleanAuthRules($module['name']);
                if ($this->addAuthRule($auth_rule)) {
                    $log .= '权限成功导入。<br/>';
                }
                //设置默认的权限
                $default_rule = json_decode($data['default_rule'], true);
                if ($this->addDefaultRule($default_rule, $module['name'])) {
                    $log .= '默认权限设置成功。<br/>';
                }
            }

            //导入
            $action = json_decode($data['action'], true);
            if (!empty($action)) {
                $this->cleanAction($module['name']);
                if ($this->addAction($action)) {
                    $log .= '行为成功导入。<br/>';
                }
            }

            $action_limit = json_decode($data['action_limit'], true);
            if (!empty($action_limit)) {
                $this->cleanActionLimit($module['name']);
                if ($this->addActionLimit($action_limit)) {
                    $log .= '行为限制成功导入。<br/>';
                }
            }

            if (file_exists(APP_PATH . '/' . $module['name'] . '/Info/install.sql')) {
                $install_sql = APP_PATH . '/' . $module['name'] . '/Info/install.sql';
                if (D()->executeSqlFile($install_sql) === true) {
                    $log .= '模块数据添加成功。';
                }
            }
        }

        $module['is_setup'] = 1;
        $this->save($module);
        $this->cleanModulesCache();//清除全站缓存
        return true;
    }

    private function addDefaultRule($default_rule, $module_name)
    {
        foreach ($default_rule as $v) {
            $rule = M('AuthRule')->where(array('module' => $module_name, 'name' => $v))->find();
            if ($rule) {
                $default[] = $rule;
            }
        }
        $auth_id = getSubByKey($default, 'id');
        $groups = M('AuthGroup')->select();
        foreach ($groups as $g) {
            $old = explode(',', $g['rules']);
            $new = array_merge($old, $auth_id);
            $g['rules'] = implode(',', $new);
            M('AuthGroup')->save($g);
        }
        return true;
    }

    private function addAction($action)
    {
        foreach ($action as $v) {
            unset($v['id']);
            M('Action')->add($v);
        }
        return true;
    }

    private function addActionLimit($action)
    {
        foreach ($action as $v) {
            unset($v['id']);
            M('ActionLimit')->add($v);
        }
        return true;
    }

    private function addAuthRule($auth_rule)
    {
        foreach ($auth_rule as $v) {
            unset($v['id']);
            M('AuthRule')->add($v);
        }
        return true;
    }

    private function cleanActionLimit($module_name)
    {
        $db_prefix = C('DB_PREFIX');
        $sql = "DELETE FROM `{$db_prefix}action_limit` where `module` = '" . $module_name . "'";
        D()->execute($sql);
    }

    private function cleanAction($module_name)
    {
        $db_prefix = C('DB_PREFIX');
        $sql = "DELETE FROM `{$db_prefix}action` where `module` = '" . $module_name . "'";
        D()->execute($sql);
    }

    private function cleanAuthRules($module_name)
    {
        $db_prefix = C('DB_PREFIX');
        $sql = "DELETE FROM `{$db_prefix}auth_rule` where `module` = '" . $module_name . "'";
        D()->execute($sql);
    }

    private function cleanMenus($module_name)
    {
        $db_prefix = C('DB_PREFIX');
        $sql = "DELETE FROM `{$db_prefix}menu` where `url` like '" . $module_name . "/%'";
        D()->execute($sql);
    }

    private function addMenus($menu, $pid = 0)
    {
        $menu['pid'] = $pid;
        unset($menu['id']);
        $id = M('Menu')->add($menu);
        $menu['id'] = $id;
        if (!empty($menu['_']))
            foreach ($menu['_'] as $v) {
                $this->addMenus($v, $id);
            }
        return true;
    }

    /**检查模块是否已安装
     * @param $name
     * @auth 陈一枭
     */
    public function getModule($name)
    {
        $module = $this->where(array('name' => $name))->cache('common_module_' . strtolower($name))->find();
        if ($module === false || $module == null) {
            $m = $this->getInfo($name);
            if ($m != array()) {
                if (intval($m['can_uninstall']) == 1) {
                    $m['is_setup'] = 0;//默认设为已安装，防止已安装的模块反复安装。
                } else {
                    $m['is_setup'] = 1;
                }
                $m['id'] = $this->add($m);
                return $m;
            }

        } else {
            return $module;
        }
    }

    public function getModuleById($id)
    {
        $module = $this->where(array('id' => $id))->find();
        if ($module === false || $module == null) {
            $m = $this->getInfo($module['name']);
            if ($m != array()) {
                if ($m['can_uninstall']) {
                    $m['is_setup'] = 0;//默认设为已安装，防止已安装的模块反复安装。
                } else {
                    $m['is_setup'] = 1;
                }
                $m['id'] = $this->add($m);
                return $m;
            }

        } else {
            return $module;
        }
    }

    public function cleanModuleCache($name)
    {
        S('common_module_' . strtolower($name), null);

    }

    private function getInfo($name)
    {
        if (file_exists(APP_PATH . '/' . $name . '/Info/info.php')) {
            $module = require(APP_PATH . '/' . $name . '/Info/info.php');
            return $module;
        } else {
            return array();
        }

    }

    /**
     * 获取文件列表
     */
    private function getFile($folder)
    {
        //打开目录
        $fp = opendir($folder);
        //阅读目录
        while (false != $file = readdir($fp)) {
            //列出所有文件并去掉'.'和'..'
            if ($file != '.' && $file != '..') {
                //$file="$folder/$file";
                $file = "$file";

                //赋值给数组
                $arr_file[] = $file;

            }
        }
        //输出结果
        if (is_array($arr_file)) {
            while (list($key, $value) = each($arr_file)) {
                $files[] = $value;
            }
        }
        //关闭目录
        closedir($fp);
        return $files;


    }


    public function isInstalled($name)
    {
        $module = $this->getModule($name);
        if ($module['is_setup']) {
            return true;
        } else {
            return false;
        }
    }
} 