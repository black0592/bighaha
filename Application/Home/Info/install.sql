INSERT INTO `ocenter_menu` (`title`, `pid`, `sort`, `url`, `hide`, `tip`, `group`, `is_dev`, `icon`) VALUES
( '网站主页', 0, 0, 'Home/config', 1, '', '', 0, 'home');

set @tmp_id=0;
select @tmp_id:= id from `ocenter_menu` where title = '网站主页';

INSERT INTO `ocenter_menu` ( `title`, `pid`, `sort`, `url`, `hide`, `tip`, `group`, `is_dev`, `icon`) VALUES
( '基本设置', @tmp_id, 0, 'Home/config', 0, '', '设置', 0, '');
