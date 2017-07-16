var scaleCreation = {
    'tables': null,
    'selectedTable': null,
    'tableData': null,
    'selectedColumn': null
};

$(document).ready(function () {
    $(".table-select-box").change(function () {
        scaleCreation.selectedTable = $(this).val();
    });

    $(".table-name-input").change(function () {
        scaleCreation.selectedTable = $(this).val();
        scaleCreation.tableData.table = $(this).val();
    });

    $("#sourceType").change(function () {
        var sourceType = $(this).val();
        $(".source-variant").collapse("hide");
        $(".source-variant[data-variant='" + sourceType + "']").collapse("show");
    });

    $(".choose-source-btn").click(function () {
        var tabPane = $(this).closest(".tab-pane");
        var sourceType = $("#sourceType").val();
        var form = tabPane.find(".form-to-validate[data-variant='" + sourceType + "']");

        if (!form[0].checkValidity()) {
            form.find(".submit-btn").click();
        } else {
            loadTables($(this), function (currentInstance) {
                var selectBox = $(".table-select-box");
                fillSelectBox(selectBox, scaleCreation.tables, scaleCreation.selectedTable);

                var tabIndex = tabPane.index() + 1;
                $(".nav-tabs li").removeClass("active").addClass("disabled").eq(tabIndex)
                    .removeClass("disabled").addClass("active");
                currentInstance.parent().find(".next-tab-hidden-btn").click();
            });
        }
    });

    $(".define-scale-btn").click(function () {
        var tabPane = $(this).closest(".tab-pane");
        var form = tabPane.find(".form-to-validate");

        if (!form[0].checkValidity()) {
            form.find(".submit-btn").click();
        } else {
            loadTableData($(this), function (currentInstance) {
                var value = $("#scale-type-select-box").val();
                $(".scale-type-form").hide().filter("[data-scale-type='" + value + "']").show();

                prepareScaleDefinitionForm();

                var tabIndex = tabPane.index() + 1;
                $(".nav-tabs li").removeClass("active").addClass("disabled").eq(tabIndex)
                    .removeClass("disabled").addClass("active");
                currentInstance.parent().find(".next-tab-hidden-btn").click();
            });
        }
    });

    $(".next-tab").click(function () {
        var tabPane = $(this).closest(".tab-pane");
        var form = tabPane.find(".form-to-validate");

        if (!form[0].checkValidity()) {
            form.find(".submit-btn").click();
        } else {
            var tabIndex = tabPane.index() + 1;
            $(".nav-tabs li").removeClass("active").addClass("disabled").eq(tabIndex)
                .removeClass("disabled").addClass("active");
            $(this).parent().find(".next-tab-hidden-btn").click();
        }
    });

    $(".prev-tab").click(function () {
        var tabPane = $(this).closest(".tab-pane");
        var tabIndex = tabPane.index() - 1;
        $(".nav-tabs li").removeClass("active").addClass("disabled").eq(tabIndex)
            .removeClass("disabled").addClass("active");
        $(this).parent().find(".prev-tab-hidden-btn").click();
    });

    $(".create-new-scale-page")
        .on("change", "#databaseConnection", function () {
            var value = $(this).val();

            if (value === "add-new-database-connection") {
                $("#create-database-connection").collapse("show");
            } else {
                $("#create-database-connection").collapse("hide");
            }
        })
        .on("click", ".save-database-connection", function (event) {
            event.preventDefault();

            var form = $(this).closest(".create-database-connection-form");
            var url = form.attr("action");

            $.ajax(url, {
                method: "post",
                data: form.serialize(),
                success: function (response) {
                    if (response.success) {
                        $("#create-database-connection").collapse("hide");
                        $("<option value=\"" + response.data.databaseConnection.id + "\">" +
                            response.data.databaseConnection.name +
                            "</option>").appendTo("#databaseConnection").prop('selected', true)
                    } else {
                        alert(response.error);
                    }
                }
            })
        })
        .on("change", "#nominal-scale-column", function () {
            var column = $(this).val();
            scaleCreation.selectedColumn = column;
            var scaleValues = $("#nominalScaleValues");
            scaleValues.find("option").remove();

            var values = [];
            for (var index in scaleCreation.tableData.data) {
                var value = scaleCreation.tableData.data[index][column];
                if ($.inArray(value, values) === -1) {
                    values.push(value);
                    $("<option value='" + value + "'>" + value + "</option>").appendTo(scaleValues);
                }
            }

            scaleValues.val(column);

            $(".nominal-scale-part-2").collapse("show");
        })
        .on("change", "#ordinal-scale-column", function () {
            scaleCreation.selectedColumn = $(this).val();

            $(".ordinal-scale-part-2").collapse("show");
        })
        .on("change", "#inter-ordinal-scale-column", function () {
            scaleCreation.selectedColumn = $(this).val();

            $(".inter-ordinal-scale-part-2").collapse("show");
        })
        .on("change", "#grid-first-scale-column", function () {
            scaleCreation.selectedColumn = $(this).val();

            $(".grid-first-scale-part-2").collapse("show");
        })
        .on("change", "#grid-second-scale-column", function () {
            scaleCreation.selectedColumn = $(this).val();

            $(".grid-second-scale-part-2").collapse("show");
        })
        .on("click", ".scale-add-value", function (event) {
            event.preventDefault();
            var parent = $(this).closest(".scale-elements");
            var prefix = parent.data("prefix");
            var input = parent.find(".scale-elements-input");
            var value = input.val();

            if (value !== "") {
                var scaleValueslist = parent.find(".scale-values-list");
                $("<li>").addClass("list-group-item").html($("<span>").text(value))
                    .append("<button class=\"btn btn-xs btn-danger pull-right scale-remove-value\">Remove</button>")
                    .appendTo(scaleValueslist);
                $("<input>").attr("type", "hidden").attr("name", prefix + "ScaleValues[]")
                    .addClass(prefix + "-scale-values").val(value).appendTo(parent);

                input.val("");

                scaleValueslist.children('li').sort(function (a, b) {
                    var val1 = parseFloat($(a).find("span").text());
                    var val2 = parseFloat($(b).find("span").text());
                    return val1 > val2;
                }).appendTo(scaleValueslist);
            }
        })
        .on("click", ".scale-remove-value", function (event) {
            event.preventDefault();
            var parent = $(this).closest(".scale-elements");
            var prefix = parent.data("prefix");
            var listItem = $(this).parent();
            var value = listItem.find("span").text();

            listItem.remove();
            parent.find("input." + prefix + "-scale-values[value='" + value + "']").remove();
        })
        .on("click", ".btn-create-nominal-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            submitScaleForm(form);
        })
        .on("click", ".btn-create-ordinal-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            submitScaleForm(form);
        })
        .on("click", ".btn-create-inter-ordinal-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            submitScaleForm(form);
        })
        .on("click", ".btn-create-grid-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            submitScaleForm(form);
        })
        .on("click", ".btn-create-custom-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            submitScaleForm(form, $(this), function (currentInstance) {
                var tablesContainer = form.find(".relation-tables");
                var table = tablesContainer.find(".create-context-table:first");
                table.find(".top-head-cell:not(:last)").each(function () {
                    var val = $.trim($(this).find("input").val());
                    var input = $("<input>").attr("type", "hidden").attr("name", "attributes[]").val(val);

                    form.append(input);
                });

                table.find(".left-head-cell:not(:last)").each(function () {
                    var val = $.trim($(this).find("input").val());
                    var input = $("<input>").attr("type", "hidden").attr("name", "objects[]").val(val);

                    form.append(input);
                });

                var firstRow = table.find(".first-row");
                table.find("tr:not(.first-row)").each(function () {
                    var row = $(this);
                    var objectName = row.find(".left-head-cell input").val();

                    row.find(".data-cell").each(function (i) {
                        if ($.trim($(this).text()) === "X") {
                            var attributeCell = firstRow.find(".top-head-cell").eq(i);
                            var attributeName = attributeCell.find("input").val();

                            var val = objectName + "###" + attributeName;
                            var input = $("<input>").attr("type", "hidden").attr("name", "relation_tuples[]").val(val);
                            form.append(input);
                        }
                    });
                });
            });
        })
        .on("change", "input.subType", function () {
            if ($(this).val() === "simple") {
                $(".nominal-scale-elements-selector").collapse("hide");
            } else {
                var column = $("#nominal-scale-column").val();
                if ($.inArray(column, scaleCreation.tableData.columns) !== -1) {
                    $(".nominal-scale-elements-selector").collapse("show");
                } else {
                    $('.subType[value="simple"]').click();
                    alert("Please select a column first.");
                }
            }
        });
});

function loadTableData(currentInstance, callback) {
    if (scaleCreation.tableData === null || scaleCreation.selectedTable !== scaleCreation.tableData.table) {
        showLoadingOverlay(currentInstance, function () {
            var url = $(".create-new-scale-page").data("get-table-data-url");
            var databaseConnection = $.trim($("#databaseConnection").val());
            var tableName = $.trim($(".table-select-box").val());

            $.ajax(url, {
                method: "get",
                data: {
                    'databaseConnectionId': databaseConnection,
                    'table': tableName
                },
                success: function (response) {
                    if (response.success) {
                        scaleCreation.tableData = response.data.tableData;

                        if (typeof(callback) === "function") {
                            callback(currentInstance);
                        }
                    }

                    hideLoadingOverlay();
                }
            });
        });
    } else {
        if (typeof(callback) === "function") {
            callback(currentInstance);
        }
    }
}

function loadTables(currentInstance, callback) {
    if (scaleCreation.tables === null) {
        var sourceType = $("#sourceType").val();
        if (sourceType !== "database") {
            $('.upload-csv-file-form').submit();
        } else {
            showLoadingOverlay(currentInstance, function (currentInstance) {
                var url = $(".create-new-scale-page").data("get-tables-url");
                var databaseConnection = $.trim($("#databaseConnection").val());

                $.ajax(url, {
                    method: "get",
                    data: {
                        'databaseConnectionId': databaseConnection
                    },
                    success: function (response) {
                        if (response.success) {
                            scaleCreation.tables = response.data.tables;

                            if (typeof(callback) === "function") {
                                callback(currentInstance);
                            }
                        }

                        hideLoadingOverlay();
                    }
                });
            });
        }
    } else {
        if (typeof(callback) === "function") {
            callback(currentInstance);
        }
    }
}

function prepareScaleDefinitionForm() {
    var scaleType = $.trim($("#scale-type-select-box").val());
    var selectBox;

    switch (scaleType) {
        case "nominal":
            selectBox = $("#nominal-scale-column");
            fillSelectBox(selectBox, scaleCreation.tableData.columns, scaleCreation.selectedColumn);
            break;
        case "ordinal":
            selectBox = $("#ordinal-scale-column");
            fillSelectBox(selectBox, scaleCreation.tableData.columns, scaleCreation.selectedColumn);
            break;
        case "inter-ordinal":
            selectBox = $("#inter-ordinal-scale-column");
            fillSelectBox(selectBox, scaleCreation.tableData.columns, scaleCreation.selectedColumn);
            break;
        case "grid":
            selectBox = $("#grid-first-scale-column");
            fillSelectBox(selectBox, scaleCreation.tableData.columns, scaleCreation.selectedColumn);

            selectBox = $("#grid-second-scale-column");
            fillSelectBox(selectBox, scaleCreation.tableData.columns, scaleCreation.selectedColumn);
            break;
        case "custom":
            break;
    }
}

function fillSelectBox(selectBox, values, selectedValue) {
    selectBox.find("option:not(:disabled)").remove();
    for (var index in values) {
        var value = values[index];
        $("<option value='" + value + "'>" + value + "</option>").appendTo(selectBox);
    }

    selectBox.val(selectedValue);
}

function submitScaleForm(form, currentInstance, callback) {
    if (!form[0].checkValidity()) {
        form.find(".submit-btn").click();
    } else {
        var tableName = $.trim($("#tableName").val());
        $("<input>").attr("type", "hidden").attr("name", "tableName").val(tableName).appendTo(form);
        var scaleName = $.trim($("#scaleName").val());
        $("<input>").attr("type", "hidden").attr("name", "scaleName").val(scaleName).appendTo(form);
        var scaleType = $.trim($("#scale-type-select-box").val());
        $("<input>").attr("type", "hidden").attr("name", "scaleType").val(scaleType).appendTo(form);
        var sourceType = $.trim($("#sourceType").val());
        $("<input>").attr("type", "hidden").attr("name", "sourceType").val(sourceType).appendTo(form);

        if (sourceType === "database") {
            var databaseConnection = $.trim($("#databaseConnection").val());
            $("<input>").attr("type", "hidden").attr("name", "databaseConnectionId").val(databaseConnection).appendTo(form);
        } else {
            var csvFileName = $.trim($("#tempCsvFile").val());
            $("<input>").attr("type", "hidden").attr("name", "csvFileName").val(csvFileName).appendTo(form);
        }

        if (typeof(callback) === "function") {
            callback(currentInstance);
        }

        form.submit();
    }
}
