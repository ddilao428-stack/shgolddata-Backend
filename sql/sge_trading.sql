/*
 SGE 贵金属/期货交易平台 - 数据库迁移脚本
 
 说明：
 - 对已有的 fa_user 表使用 ALTER TABLE 扩展字段
 - 新建 18 张业务/功能表
 - 复用 FastAdmin 已有表：fa_config, fa_user_token, fa_ems, fa_sms, fa_admin_log
 
 Date: 2026-03-01
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. 扩展已有 fa_user 表（ALTER TABLE，不重建）
-- ============================================================
ALTER TABLE `fa_user`
  ADD COLUMN `trade_password` varchar(255) NOT NULL DEFAULT '' COMMENT '提现密码' AFTER `password`,
  ADD COLUMN `is_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '实名认证状态:0=未认证,1=已认证,2=审核中,3=已拒绝' AFTER `gender`,
  ADD COLUMN `language` varchar(10) NOT NULL DEFAULT 'zh-cn' COMMENT '语言偏好' AFTER `is_verified`;

-- ============================================================
-- 2. fa_product — 产品表
-- ============================================================
DROP TABLE IF EXISTS `fa_product`;
CREATE TABLE `fa_product` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '产品ID',
  `product_code` varchar(20) NOT NULL DEFAULT '' COMMENT '产品唯一编码',
  `capital_key` varchar(50) NOT NULL DEFAULT '' COMMENT '数据源标识',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '产品名称',
  `name_en` varchar(50) NOT NULL DEFAULT '' COMMENT '英文名称',
  `data_source` varchar(30) NOT NULL DEFAULT 'External' COMMENT '数据源类型:External=外部,CoinGecko=CoinGecko,Random=随机',
  `category_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类ID',
  `currency` varchar(10) NOT NULL DEFAULT 'CNY' COMMENT '货币类型',
  `price_decimals` tinyint(2) NOT NULL DEFAULT 2 COMMENT '价格小数位数',
  `dot_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '每点波动金额',
  `dot_unit` int(11) NOT NULL DEFAULT 1 COMMENT '波动点数',
  `price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '当前价格',
  `open_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '今开',
  `close_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '昨收',
  `high_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '最高',
  `low_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '最低',
  `diff` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '涨跌额',
  `diff_rate` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '涨跌幅(%)',
  `buy_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '买入价',
  `sell_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '卖出价',
  `buy_volume` int(11) NOT NULL DEFAULT 0 COMMENT '买入量',
  `sell_volume` int(11) NOT NULL DEFAULT 0 COMMENT '卖出量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '交易状态:0=关闭,1=开启',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `time_config` text COMMENT '时间盘配置JSON',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '产品图标',
  `price_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '价格更新时间戳',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `idx_capital_key` (`capital_key`),
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品表';

-- ============================================================
-- 3. fa_product_category — 产品分类表
-- ============================================================
DROP TABLE IF EXISTS `fa_product_category`;
CREATE TABLE `fa_product_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
  `name_en` varchar(50) NOT NULL DEFAULT '' COMMENT '英文名称',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品分类表';

-- ============================================================
-- 4. fa_product_trade_time — 产品交易时间表
-- ============================================================
DROP TABLE IF EXISTS `fa_product_trade_time`;
CREATE TABLE `fa_product_trade_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `product_id` int(11) NOT NULL DEFAULT 0 COMMENT '产品ID',
  `start_time` char(4) NOT NULL DEFAULT '' COMMENT '开始时间(如0915)',
  `end_time` char(4) NOT NULL DEFAULT '' COMMENT '结束时间(如1200)',
  `is_overnight` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否跨天:0=否,1=是',
  `sort` tinyint(2) NOT NULL DEFAULT 1 COMMENT '时段排序',
  PRIMARY KEY (`id`),
  INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品交易时间表';

-- ============================================================
-- 5. fa_user_account — 用户资金账户表
-- ============================================================
DROP TABLE IF EXISTS `fa_user_account`;
CREATE TABLE `fa_user_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `balance` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '可用余额',
  `frozen` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '冻结金额',
  `total_recharge` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '累计充值',
  `total_withdraw` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '累计提现',
  `total_profit` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '累计盈利',
  `total_loss` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '累计亏损',
  `updatetime` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户资金账户表';

-- ============================================================
-- 6. fa_order — 订单表
-- ============================================================
DROP TABLE IF EXISTS `fa_order`;
CREATE TABLE `fa_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单编号',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `product_id` int(11) NOT NULL DEFAULT 0 COMMENT '产品ID',
  `direction` tinyint(1) NOT NULL DEFAULT 0 COMMENT '交易方向:0=买涨,1=买跌',
  `open_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '入仓价格',
  `close_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '结算价格',
  `trade_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '交易金额',
  `profit` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '盈亏金额',
  `odds` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '收益率(%)',
  `duration` int(11) NOT NULL DEFAULT 0 COMMENT '持仓时长(秒)',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0=持仓中,1=已结算,2=已取消',
  `result` tinyint(1) NULL DEFAULT NULL COMMENT '结果:0=亏损,1=盈利,2=平局',
  `open_time` int(11) NOT NULL DEFAULT 0 COMMENT '下单时间',
  `close_time` int(11) NULL DEFAULT NULL COMMENT '结算时间',
  `settle_time` int(11) NOT NULL DEFAULT 0 COMMENT '预计结算时间戳',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_order_no` (`order_no`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_settle_time` (`settle_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

-- ============================================================
-- 7. fa_money_flow — 资金流水表
-- ============================================================
DROP TABLE IF EXISTS `fa_money_flow`;
CREATE TABLE `fa_money_flow` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `type` tinyint(2) NOT NULL DEFAULT 0 COMMENT '类型:1=充值,2=提现,3=下单扣款,4=结算返还,5=管理员调整',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动金额(正为入负为出)',
  `balance` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
  `related_id` varchar(32) NOT NULL DEFAULT '' COMMENT '关联ID(订单号/充值单号等)',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_type` (`type`),
  INDEX `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资金流水表';

-- ============================================================
-- 8. fa_recharge — 充值记录表
-- ============================================================
DROP TABLE IF EXISTS `fa_recharge`;
CREATE TABLE `fa_recharge` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '充值单号',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '充值金额',
  `pay_type` varchar(20) NOT NULL DEFAULT '' COMMENT '支付方式',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0=待支付,1=已完成,2=已取消',
  `pay_time` int(11) NULL DEFAULT NULL COMMENT '支付时间',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_order_no` (`order_no`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值记录表';

-- ============================================================
-- 9. fa_withdraw — 提现记录表
-- ============================================================
DROP TABLE IF EXISTS `fa_withdraw`;
CREATE TABLE `fa_withdraw` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '提现单号',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '提现金额',
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '手续费',
  `bank_id` int(11) NOT NULL DEFAULT 0 COMMENT '银行卡ID',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0=待审核,1=已通过,2=已拒绝,3=已打款',
  `audit_time` int(11) NULL DEFAULT NULL COMMENT '审核时间',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_order_no` (`order_no`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录表';

-- ============================================================
-- 10. fa_user_bank — 用户银行卡表
-- ============================================================
DROP TABLE IF EXISTS `fa_user_bank`;
CREATE TABLE `fa_user_bank` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `bank_name` varchar(50) NOT NULL DEFAULT '' COMMENT '银行名称',
  `card_no` varchar(30) NOT NULL DEFAULT '' COMMENT '银行卡号',
  `holder_name` varchar(50) NOT NULL DEFAULT '' COMMENT '持卡人姓名',
  `branch` varchar(100) NOT NULL DEFAULT '' COMMENT '开户支行',
  `province` varchar(30) NOT NULL DEFAULT '' COMMENT '省份',
  `city` varchar(30) NOT NULL DEFAULT '' COMMENT '城市',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=正常',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户银行卡表';

-- ============================================================
-- 11. fa_user_verify — 实名认证表
-- ============================================================
DROP TABLE IF EXISTS `fa_user_verify`;
CREATE TABLE `fa_user_verify` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `real_name` varchar(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `id_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '证件类型:1=身份证,2=驾驶证,3=SSN,4=护照',
  `id_card` varchar(30) NOT NULL DEFAULT '' COMMENT '证件号码',
  `id_card_front` varchar(255) NOT NULL DEFAULT '' COMMENT '证件正面照',
  `id_card_back` varchar(255) NOT NULL DEFAULT '' COMMENT '证件背面照',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '审核状态:0=待审核,1=已通过,2=已拒绝',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `audit_time` int(11) NULL DEFAULT NULL COMMENT '审核时间',
  `reject_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '拒绝原因',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '提交时间',
  `updatetime` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='实名认证表';

-- ============================================================
-- 12. fa_announcement — 公告表
-- ============================================================
DROP TABLE IF EXISTS `fa_announcement`;
CREATE TABLE `fa_announcement` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `content` text COMMENT '内容',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `start_time` int(11) NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` int(11) NULL DEFAULT NULL COMMENT '结束时间',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- ============================================================
-- 13. fa_kline — K线数据表
-- ============================================================
DROP TABLE IF EXISTS `fa_kline`;
CREATE TABLE `fa_kline` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `product_id` int(11) NOT NULL DEFAULT 0 COMMENT '产品ID',
  `period` varchar(10) NOT NULL DEFAULT '' COMMENT '周期:1m=1分钟,5m=5分钟,30m=30分钟,60m=60分钟,1d=1天',
  `open_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '开盘价',
  `close_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '收盘价',
  `high_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '最高价',
  `low_price` decimal(16,6) NOT NULL DEFAULT 0.000000 COMMENT '最低价',
  `volume` int(11) NOT NULL DEFAULT 0 COMMENT '成交量',
  `timestamp` int(11) NOT NULL DEFAULT 0 COMMENT '时间戳',
  PRIMARY KEY (`id`),
  INDEX `idx_product_period_time` (`product_id`, `period`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='K线数据表';

-- ============================================================
-- 14. fa_banner — 轮播图表
-- ============================================================
DROP TABLE IF EXISTS `fa_banner`;
CREATE TABLE `fa_banner` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片地址',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转链接',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轮播图表';

-- ============================================================
-- 15. fa_user_favorite — 用户收藏(自选)表
-- ============================================================
DROP TABLE IF EXISTS `fa_user_favorite`;
CREATE TABLE `fa_user_favorite` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `product_id` int(11) NOT NULL DEFAULT 0 COMMENT '产品ID',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '收藏时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户收藏表';

-- ============================================================
-- 16. fa_news_category — 新闻分类表
-- ============================================================
DROP TABLE IF EXISTS `fa_news_category`;
CREATE TABLE `fa_news_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
  `name_en` varchar(50) NOT NULL DEFAULT '' COMMENT '英文名称',
  `flag` varchar(30) NOT NULL DEFAULT '' COMMENT '分类标识',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='新闻分类表';

-- ============================================================
-- 17. fa_news — 新闻资讯表
-- ============================================================
DROP TABLE IF EXISTS `fa_news`;
CREATE TABLE `fa_news` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `category_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类ID',
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '标题',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` mediumtext COMMENT '内容',
  `author` varchar(50) NOT NULL DEFAULT '' COMMENT '作者',
  `views` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=草稿,1=已发布',
  `publish_time` int(11) NULL DEFAULT NULL COMMENT '发布时间',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_publish_time` (`publish_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='新闻资讯表';

-- ============================================================
-- 18. fa_finance_product — 理财产品表
-- ============================================================
DROP TABLE IF EXISTS `fa_finance_product`;
CREATE TABLE `fa_finance_product` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
  `min_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '最低转入金额',
  `daily_rate` decimal(8,4) NOT NULL DEFAULT 0.0000 COMMENT '日收益率',
  `lock_days` int(11) NOT NULL DEFAULT 0 COMMENT '锁仓周期(天)',
  `open_time` char(5) NOT NULL DEFAULT '' COMMENT '每日开放时间(如10:00)',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0=关闭,1=开启',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='理财产品表';

-- ============================================================
-- 19. fa_finance_order — 理财锁仓记录表
-- ============================================================
DROP TABLE IF EXISTS `fa_finance_order`;
CREATE TABLE `fa_finance_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单编号',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `product_id` int(11) NOT NULL DEFAULT 0 COMMENT '理财产品ID',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '转入金额',
  `daily_rate` decimal(8,4) NOT NULL DEFAULT 0.0000 COMMENT '锁定时的日收益率',
  `lock_days` int(11) NOT NULL DEFAULT 0 COMMENT '锁仓天数',
  `total_profit` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计收益',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0=锁仓中,1=已到期,2=已提前赎回',
  `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '锁仓开始时间',
  `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '预计到期时间',
  `settle_time` int(11) NULL DEFAULT NULL COMMENT '实际结算时间',
  `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_order_no` (`order_no`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_end_time` (`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='理财锁仓记录表';

-- ============================================================
-- 初始数据
-- ============================================================

-- 产品分类初始数据
INSERT INTO `fa_product_category` (`id`, `name`, `name_en`, `sort`, `status`, `createtime`) VALUES
(1, '国内期货', 'Domestic Futures', 1, 1, UNIX_TIMESTAMP()),
(2, '国际期货', 'International Futures', 2, 1, UNIX_TIMESTAMP()),
(3, '外汇', 'Forex', 3, 1, UNIX_TIMESTAMP()),
(4, '贵金属', 'Precious Metals', 4, 1, UNIX_TIMESTAMP()),
(5, '数字货币', 'Cryptocurrency', 5, 1, UNIX_TIMESTAMP());

-- 新闻分类初始数据
INSERT INTO `fa_news_category` (`id`, `name`, `name_en`, `flag`, `sort`, `status`, `createtime`) VALUES
(1, '综合资讯', 'General News', 'general', 1, 1, UNIX_TIMESTAMP()),
(2, '学院教程', 'Academy', 'academy', 2, 1, UNIX_TIMESTAMP());

-- 系统配置初始数据（写入 fa_config 表，使用 trade 分组）
-- 先更新 configgroup 增加 trade 分组
UPDATE `fa_config` SET `value` = '{\"basic\":\"Basic\",\"email\":\"Email\",\"dictionary\":\"Dictionary\",\"user\":\"User\",\"example\":\"Example\",\"trade\":\"Trade\"}' WHERE `name` = 'configgroup';

INSERT INTO `fa_config` (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`) VALUES
('platform_name', 'trade', '平台名称', '前端显示的平台名称', 'string', '', 'SGE贵金属交易所', '', 'required', '', ''),
('service_link', 'trade', '客服链接', '在线客服跳转链接', 'string', '', '', '', '', '', ''),
('exchange_rate_usd', 'trade', '美元汇率', '美元兑人民币汇率', 'string', '', '7.25', '', 'required', '', ''),
('exchange_rate_hkd', 'trade', '港币汇率', '港币兑人民币汇率', 'string', '', '0.93', '', 'required', '', ''),
('withdraw_fee_rate', 'trade', '提现手续费率', '提现手续费百分比(如2表示2%)', 'string', '', '2', '', '', '', ''),
('min_withdraw', 'trade', '最低提现金额', '单次最低提现金额', 'string', '', '100', '', '', '', ''),
('market_ws_url', 'trade', '行情数据源地址', 'WebSocket行情数据源URL', 'string', '', 'ws://39.107.99.235:8889', '', '', '', '');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 后台管理菜单补充（fa_auth_rule）
-- 注意：php think crud -u 1 已自动生成基础CRUD菜单
-- 以下仅补充自定义操作权限和分组菜单（使用INSERT IGNORE避免重复）
-- ============================================================

-- 补充自定义操作权限（CRUD不会自动生成的）
-- 产品管理：交易时间配置、时间盘配置
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='product' LIMIT 1), 'product/tradetime', '交易时间配置', 'fa fa-clock-o', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='product' LIMIT 1), 'product/timeconfig', '时间盘配置', 'fa fa-cog', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 订单管理：手动结算
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='order' LIMIT 1), 'order/settle', '手动结算', 'fa fa-calculator', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 实名认证：审核通过、审核拒绝
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='verify' LIMIT 1), 'verify/approve', '审核通过', 'fa fa-check', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='verify' LIMIT 1), 'verify/reject', '审核拒绝', 'fa fa-times', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 充值管理：审核通过、审核拒绝
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='recharge' LIMIT 1), 'recharge/approve', '审核通过', 'fa fa-check', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='recharge' LIMIT 1), 'recharge/reject', '审核拒绝', 'fa fa-times', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 提现管理：审核通过、审核拒绝、确认打款
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='withdraw' LIMIT 1), 'withdraw/approve', '审核通过', 'fa fa-check', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='withdraw' LIMIT 1), 'withdraw/reject', '审核拒绝', 'fa fa-times', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='withdraw' LIMIT 1), 'withdraw/confirm', '确认打款', 'fa fa-money', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 理财锁仓：手动结算
INSERT IGNORE INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `createtime`, `updatetime`, `weigh`, `status`) VALUES
('file', (SELECT `id` FROM `fa_auth_rule` WHERE `name`='financeorder' LIMIT 1), 'financeorder/settle', '手动结算', 'fa fa-calculator', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

-- 注意：内容管理（news/newscategory/banner/announcement）和理财管理（financeproduct/financeorder）
-- 的基础CRUD菜单已由 php think crud -u 1 自动生成，无需重复插入

-- 给超级管理员组授权所有自定义操作权限
-- 先查出新插入的自定义权限ID，拼接到管理员组的rules中
-- 实际部署时请在数据库中手动执行或通过后台权限管理分配
