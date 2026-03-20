define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'financeorder/index' + location.search,
                    table: 'finance_order',
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
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id'), visible: false},
                        {field: 'user.username', title: __('User_id'), operate: false},
                        {field: 'product_id', title: __('Product_id'), visible: false},
                        {field: 'financeproduct.name', title: __('Product_id'), operate: false},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'daily_rate', title: __('Daily_rate'), operate:'BETWEEN'},
                        {field: 'lock_days', title: __('Lock_days')},
                        {field: 'total_profit', title: __('Total_profit'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'start_time', title: __('Start_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'end_time', title: __('End_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'settle',
                                    text: __('结算'),
                                    title: __('手动结算'),
                                    classname: 'btn btn-xs btn-warning btn-ajax',
                                    icon: 'fa fa-calculator',
                                    url: 'financeorder/settle',
                                    confirm: '确认手动结算该锁仓订单？',
                                    success: function(data, ret){ $(".btn-refresh").trigger("click"); },
                                    visible: function(row){ return row.status == 0; }
                                }
                            ],
                            formatter: function (value, row, index) {
                                var html = '';
                                if (row.status == 0) {
                                    html += '<a href="javascript:;" class="btn btn-xs btn-warning btn-ajax" data-url="financeorder/settle" data-params="ids=' + row.id + '" data-confirm="确认手动结算该锁仓订单？" title="手动结算"><i class="fa fa-calculator"></i> 结算</a>';
                                }
                                return html;
                            }
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
