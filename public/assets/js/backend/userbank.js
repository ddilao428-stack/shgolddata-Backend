define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    // 切换银行卡/USDT字段显示
    var toggleFields = function () {
        var type = $('#c-type').val();
        if (type === 'usdt') {
            $('.bank-field').hide();
            $('.usdt-field').show();
        } else {
            $('.bank-field').show();
            $('.usdt-field').hide();
        }
    };

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'userbank/index' + location.search,
                    edit_url: 'userbank/edit',
                    table: 'user_bank',
                }
            });


            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                fixedColumns: true,
                fixedRightNumber: 1,
                searchFormVisible: true,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('会员ID')},
                        {field: 'user_nickname', title: __('用户姓名'), operate: false},
                        {field: 'user_account', title: __('会员账号'), operate: false},
                        {field: 'type', title: __('类型'), searchList: {"bank":"银行卡","usdt":"USDT"}, formatter: function(value){var m={"bank":"银行卡","usdt":"USDT"};return m[value]||value;}},
                        {field: 'bank_name', title: __('银行/链'), operate: 'LIKE', formatter: function(value, row){return row.type==='usdt' ? (row.chain_type||'-') : (value||'-');}},
                        {field: 'card_no', title: __('卡号/地址'), operate: 'LIKE', formatter: function(value, row){return row.type==='usdt' ? (row.wallet_address||'-') : (value||'-');}},
                        {field: 'holder_name', title: __('持卡人'), operate: 'LIKE', formatter: function(value){return value||'-';}},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            // 绑定类型切换
            $(document).on('change', '#c-type', toggleFields);
            toggleFields();
        },
        edit: function () {
            Controller.api.bindevent();
            // 绑定类型切换
            $(document).on('change', '#c-type', toggleFields);
            toggleFields();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});