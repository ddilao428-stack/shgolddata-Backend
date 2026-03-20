define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/index' + location.search,
                    // add_url: 'order/add',
                    // edit_url: 'order/edit',
                    // del_url: 'order/del',
                    // multi_url: 'order/multi',
                    // import_url: 'order/import',
                    table: 'order',
                }
            });

            // 去掉价格尾部多余的0，最少保留2位小数
            var trimZero = function(v) {
                if (!v && v !== 0) return '-';
                var n = parseFloat(v);
                var s = n.toString();
                var dot = s.indexOf('.');
                if (dot === -1) return n.toFixed(2);
                var decimals = s.length - dot - 1;
                return decimals < 2 ? n.toFixed(2) : s;
            };

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
                        {field: 'user_username', title: __('User_id'), operate: false, formatter: function(value, row) {
                            return value || '-';
                        }},
                        {field: 'product_name', title: __('Product_id'), operate: false, formatter: function(value, row) {
                            return value || '-';
                        }},
                        {field: 'direction', title: __('Direction'), searchList: {"0":__('Direction 0'),"1":__('Direction 1')}, formatter: function(value, row) {
                            if (value == 0) {
                                return '<span style="color:#e74c3c;font-weight:bold">' + __('Direction 0') + '</span>';
                            } else {
                                return '<span style="color:#2ecc71;font-weight:bold">' + __('Direction 1') + '</span>';
                            }
                        }},
                        {field: 'open_price', title: __('Open_price'), operate:'BETWEEN', formatter: function(value) { return trimZero(value); }},
                        {field: 'close_price', title: __('Close_price'), operate:'BETWEEN', formatter: function(value) {
                            if (!value || parseFloat(value) == 0) return '-';
                            return trimZero(value);
                        }},
                        {field: 'trade_amount', title: __('Trade_amount'), operate:'BETWEEN', formatter: function(value) { return trimZero(value); }},
                        {field: 'fee', title: __('Fee'), operate:'BETWEEN', formatter: function(value) { return trimZero(value); }},
                        {field: 'profit', title: __('Profit'), operate:'BETWEEN', formatter: function(value, row) {
                            var v = parseFloat(value);
                            var display = trimZero(value);
                            if (v > 0) {
                                return '<span style="color:#e74c3c;font-weight:bold">+' + display + '</span>';
                            } else if (v < 0) {
                                return '<span style="color:#2ecc71;font-weight:bold">' + display + '</span>';
                            }
                            return '<span style="color:#999">' + display + '</span>';
                        }},
                        {field: 'odds', title: __('Odds'), operate:'BETWEEN'},
                        {field: 'duration', title: __('Duration')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: function(value, row) {
                            if (value == 0) {
                                return '<span class="label label-primary">' + __('Status 0') + '</span>';
                            } else if (value == 1) {
                                return '<span class="label label-success">' + __('Status 1') + '</span>';
                            } else {
                                return '<span class="label label-default">' + __('Status 2') + '</span>';
                            }
                        }},
                        {field: 'result', title: __('Result'), searchList: {"0":__('Result 0'),"1":__('Result 1'),"2":__('Result 2')}, formatter: function(value, row) {
                            if (value == 1) {
                                return '<span class="label label-danger">' + __('Result 1') + '</span>';
                            } else if (value == 0) {
                                return '<span class="label label-success">' + __('Result 0') + '</span>';
                            } else if (value == 2) {
                                return '<span class="label label-warning">' + __('Result 2') + '</span>';
                            }
                            return '-';
                        }},
                        {field: 'open_time', title: __('Open_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'close_time', title: __('Close_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: function(value, row) {
                            if (!value || value == 0) return '-';
                            return Table.api.formatter.datetime.call(this, value, row);
                        }},
                        // {field: 'settle_time', title: __('Settle_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'settle',
                                    text: '结算',
                                    title: '手动结算',
                                    classname: 'btn btn-xs btn-warning btn-ajax',
                                    icon: 'fa fa-calculator',
                                    url: 'order/settle',
                                    confirm: '确认立即结算该订单？将使用产品当前价格作为结算价',
                                    success: function(data, ret) {
                                        $(".btn-refresh").trigger("click");
                                    },
                                    visible: function(row){ return row.status == 0; }
                                }
                            ]
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 批量结算
            $(document).on('click', '.btn-batch-settle', function () {
                var ids = Table.api.selectedids(table);
                if (!ids || ids.length === 0) {
                    Toastr.error('请先勾选要结算的订单');
                    return;
                }
                Layer.confirm('确认批量结算选中的 ' + ids.length + ' 个订单？将使用产品当前价格作为结算价', function (index) {
                    Layer.close(index);
                    $.ajax({
                        url: 'order/batchsettle',
                        type: 'POST',
                        data: {ids: ids.join(',')},
                        dataType: 'json',
                        success: function (ret) {
                            if (ret.code === 1) {
                                Toastr.success(ret.msg);
                                table.bootstrapTable('refresh');
                            } else {
                                Toastr.error(ret.msg);
                            }
                        }
                    });
                });
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
