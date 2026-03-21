-- 添加提现密码独立 salt 字段
-- 日期: 2026-03-21
-- 说明: 提现密码使用独立的 trade_salt，避免修改登录密码时导致提现密码失效

ALTER TABLE `fa_user` ADD COLUMN `trade_salt` varchar(30) DEFAULT '' COMMENT '提现密码salt' AFTER `salt`;

-- 清空用户相关表并重置自增ID
TRUNCATE TABLE `fa_user`;                -- 用户表
TRUNCATE TABLE `fa_user_token`;          -- 用户登录Token
TRUNCATE TABLE `fa_user_account`;        -- 用户账户（余额/冻结）
TRUNCATE TABLE `fa_user_bank`;           -- 用户银行卡/钱包
TRUNCATE TABLE `fa_user_money_log`;      -- 用户资金流水
TRUNCATE TABLE `fa_user_score_log`;      -- 用户积分流水
TRUNCATE TABLE `fa_user_verify`;         -- 用户实名认证
TRUNCATE TABLE `fa_recharge`;            -- 充值记录
TRUNCATE TABLE `fa_withdraw`;            -- 提现记录
TRUNCATE TABLE `fa_order`;               -- 交易订单
TRUNCATE TABLE `fa_finance_order`;       -- 理财订单
TRUNCATE TABLE `fa_finance_profit_log`;  -- 理财收益记录
