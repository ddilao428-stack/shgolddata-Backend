define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'verify/index' + location.search,
                    edit_url: 'verify/edit',
                    table: 'user_verify',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'updatetime',
                sortOrder: 'desc',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_username', title: __('User_username'), operate: false},
                        {field: 'real_name', title: __('Real_name'), operate: 'LIKE'},
                        {field: 'id_type_text', title: __('Id_type'), operate: false},
                        {field: 'id_card', title: __('Id_card'), operate: 'LIKE'},
                        {field: 'id_card_front', title: __('Id_card_front'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'id_card_back', title: __('Id_card_back'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'audit_time', title: __('Audit_time'), operate: false, formatter: function(value) {
                            if (!value) return '-';
                            var d = new Date(value * 1000);
                            var pad = function(n){ return n < 10 ? '0'+n : n; };
                            return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+' '+pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds());
                        }},
                        {field: 'reject_reason', title: __('Reject_reason'), operate: false, formatter: function(value) { return value || '-'; }},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime, sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'approve',
                                    text: __('审核通过'),
                                    title: __('审核通过'),
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-check',
                                    url: 'verify/approve',
                                    confirm: '确认审核通过该实名认证？',
                                    success: function(data, ret){ $(".btn-refresh").trigger("click"); },
                                    visible: function(row){ return row.status == 0; }
                                },
                                {
                                    name: 'reject',
                                    text: __('拒绝'),
                                    title: __('拒绝认证'),
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    icon: 'fa fa-times',
                                    url: 'verify/reject',
                                    visible: function(row){ return row.status == 0; }
                                }
                            ]
                        }
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        reject: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
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
