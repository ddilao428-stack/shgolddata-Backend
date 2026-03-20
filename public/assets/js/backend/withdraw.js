define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'withdraw/index' + location.search,
                    add_url: 'withdraw/add',
                    edit_url: 'withdraw/edit',
                    del_url: 'withdraw/del',
                    multi_url: 'withdraw/multi',
                    import_url: 'withdraw/import',
                    table: 'withdraw',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'user_username', title: __('User_username'), operate: false},
                        {field: 'user_realname', title: __('User_realname'), operate: false},
                        {field: 'pay_type', title: __('Pay_type'), formatter: function(value) {
                            var map = {'bank': '银行卡', 'usdt': 'USDT'};
                            return map[value] || value;
                        }, searchList: {"bank": "银行卡", "usdt": "USDT"}},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'fee', title: __('Fee'), operate:'BETWEEN'},
                        {field: 'withdraw_account', title: __('Withdraw_account'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'audit_time', title: __('Audit_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: function(value, row, index) {
                            if (!value || value == 0) return '-';
                            return Table.api.formatter.datetime.call(this, value, row, index);
                        }},
                        {field: 'admin_username', title: __('Admin_username'), operate: false},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,
                            formatter: function(value, row, index) {
                                var html = [];
                                if (row.status == 0) {
                                    html.push('<a href="javascript:;" class="btn btn-xs btn-success btn-approve" data-id="' + row.id + '"><i class="fa fa-check"></i> 审核通过</a>');
                                    html.push('<a href="javascript:;" class="btn btn-xs btn-danger btn-dialog" data-url="withdraw/reject?ids=' + row.id + '" data-title="拒绝提现"><i class="fa fa-times"></i> 拒绝</a>');
                                }
                                return html.join(' ');
                            },
                            events: {
                                'click .btn-approve': function(e, value, row, index) {
                                    e.stopPropagation();
                                    Layer.confirm('确认审核通过该提现申请？', {icon: 3, title: '提示'}, function(layIdx) {
                                        $.post('withdraw/approve', {ids: row.id}, function(ret) {
                                            if (ret.code === 1) {
                                                Layer.close(layIdx);
                                                Toastr.success(ret.msg);
                                                $(".btn-refresh").trigger("click");
                                            } else {
                                                Toastr.error(ret.msg);
                                            }
                                        }, 'json');
                                    });
                                },
                                'click .btn-dialog': function(e, value, row, index) {
                                    e.stopPropagation();
                                    var url = $(e.currentTarget).data('url');
                                    var title = $(e.currentTarget).data('title');
                                    Fast.api.open(url, title, {
                                        callback: function() {
                                            $(".btn-refresh").trigger("click");
                                        }
                                    });
                                }
                            }
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        reject: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
