define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'product/index' + location.search,
                    add_url: 'product/add',
                    edit_url: 'product/edit',
                    del_url: 'product/del',
                    multi_url: 'product/multi',
                    import_url: 'product/import',
                    table: 'product',
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
                searchFormVisible: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'product_code', title: __('Product_code'), operate: 'LIKE'},
                        {field: 'capital_key', title: __('Capital_key'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'name_en', title: __('Name_en'), operate: 'LIKE'},
                        // {field: 'data_source', title: __('Data_source'), operate: 'LIKE'},
                        {field: 'category_name', title: __('产品分类'), operate: false},
                        // {field: 'currency', title: __('Currency'), operate: 'LIKE'},
                        {field: 'price_decimals', title: __('Price_decimals')},
                        // {field: 'dot_value', title: __('Dot_value'), operate:'BETWEEN'},
                        // {field: 'dot_unit', title: __('Dot_unit')},
                        // {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        // {field: 'open_price', title: __('Open_price'), operate:'BETWEEN'},
                        // {field: 'close_price', title: __('Close_price'), operate:'BETWEEN'},
                        // {field: 'high_price', title: __('High_price'), operate:'BETWEEN'},
                        // {field: 'low_price', title: __('Low_price'), operate:'BETWEEN'},
                        // {field: 'diff', title: __('Diff'), operate:'BETWEEN'},
                        // {field: 'diff_rate', title: __('Diff_rate'), operate:'BETWEEN'},
                        // {field: 'buy_price', title: __('Buy_price'), operate:'BETWEEN'},
                        // {field: 'sell_price', title: __('Sell_price'), operate:'BETWEEN'},
                        // {field: 'buy_volume', title: __('Buy_volume')},
                        // {field: 'sell_volume', title: __('Sell_volume')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'is_recommend', title: __('首页推荐'), searchList: {"0":__('否'),"1":__('是')}, formatter: Table.api.formatter.toggle},
                        {field: 'sort', title: __('Sort')},
                        {field: 'icon', title: __('Icon'), operate: false, formatter: Table.api.formatter.image},
                        // {field: 'price_updatetime', title: __('Price_updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'tradetime',
                                    text: __('交易时间'),
                                    title: __('交易时间配置'),
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-clock-o',
                                    url: 'product/tradetime'
                                },
                                {
                                    name: 'timeconfig',
                                    text: __('时间盘'),
                                    title: __('时间盘配置'),
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    icon: 'fa fa-cog',
                                    url: 'product/timeconfig'
                                }
                            ]
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
        tradetime: function () {
            var idx = $('#time-table tbody tr').length;
            $(document).on('click', '#btn-add-tradetime', function () {
                var html = '<tr><td><input type="text" name="times[' + idx + '][deal_time_start]" class="form-control" placeholder="如09:15"/></td>';
                html += '<td><input type="text" name="times[' + idx + '][deal_time_end]" class="form-control" placeholder="如12:00"/></td>';
                html += '<td><a href="javascript:;" class="btn btn-xs btn-danger btn-tradetime-remove">删除</a></td></tr>';
                $('#time-table tbody').append(html);
                idx++;
            });
            $(document).on('click', '.btn-tradetime-remove', function () {
                $(this).closest('tr').remove();
            });
            Form.api.bindevent($("#tradetime-form"));
        },
        timeconfig: function () {
            var idx = $('#config-table tbody tr').length;
            $(document).on('click', '#btn-add-timeconfig', function () {
                var html = '<tr><td><input type="number" name="config[' + idx + '][minute]" class="form-control"/></td>';
                html += '<td><input type="number" name="config[' + idx + '][odds]" class="form-control" step="0.01"/></td>';
                html += '<td><a href="javascript:;" class="btn btn-xs btn-danger btn-timeconfig-remove">删除</a></td></tr>';
                $('#config-table tbody').append(html);
                idx++;
            });
            $(document).on('click', '.btn-timeconfig-remove', function () {
                $(this).closest('tr').remove();
            });
            Form.api.bindevent($("#timeconfig-form"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
