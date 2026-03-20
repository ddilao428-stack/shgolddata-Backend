define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'financeprofitlog/index' + location.search,
                    table: 'finance_profit_log',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id'), visible: false},
                        {field: 'user.username', title: __('User_id'), operate: false},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'daily_rate', title: __('Daily_rate'), operate:'BETWEEN'},
                        {field: 'profit', title: __('Profit'), operate:'BETWEEN'},
                        {field: 'day_index', title: __('Day_index')},
                        {field: 'profit_date', title: __('Profit_date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime}
                    ]
                ]
            });

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
