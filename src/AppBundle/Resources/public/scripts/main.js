var conceptLattice = {
    settings: {
        collisionDetection: false,
        showTopLabels: true,
        showBottomLabels: true,
        analogicalComplexId: -1,
        collapseLabels: true,
        circleRadius: 15,
        circleRadiusVariation: 7,
        linkDistance: 100,
        textTopOffset: "-1.35em",
        textBottomOffset: "2.25em"
    }
};

var tableData = null;

$(document).ready(function () {
    var body = $("body");
    var container = $(".main-container");

    adjustMinHeight(container);

    $(window).resize(function () {
        adjustMinHeight(container);
    });

    $(".sort-filter").DataTable({
        "bPaginate": false
    });

    $.extend(conceptLattice, {
        container: $(".concept-lattice-container")
    });

    $(".next-tab").click(function() {
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

    $(".prev-tab").click(function() {
        var tabPane = $(this).closest(".tab-pane");
        var tabIndex = tabPane.index() - 1;
        $(".nav-tabs li").removeClass("active").addClass("disabled").eq(tabIndex)
            .removeClass("disabled").addClass("active");
        $(this).parent().find(".prev-tab-hidden-btn").click();
    });

    $(".panel-collapse")
        .on('show.bs.collapse', function () {
            $(this).closest(".panel").removeClass("panel-default").addClass("panel-primary");
        })
        .on('hide.bs.collapse', function () {
            $(this).closest(".panel").removeClass("panel-primary").addClass("panel-default");
        });

    $(".create-new-scale-page").on("change", "#databaseConnection", function () {
        var value = $(this).val();

        if (value === "add-new-database-connection") {
            $("#create-database-connection").collapse("show");
        } else {
            $("#create-database-connection").collapse("hide");
        }
    }).on("click", ".save-database-connection", function (event) {
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
    }).on("change", "#scale-type-select-box", function() {
        $(".scale-type-form").hide().filter("[data-scale-type='" + $(this).val() + "']").show();
    });

    $(".remove-member-btn").click(function (event) {
        event.preventDefault();
        var memberContainer = $(this).closest("tr");

        var url = $(this).attr("href");
        $.ajax(url, {
            success: function (response) {
                if (response.success == true) {
                    memberContainer.remove();
                } else {
                    alert(response.error);
                }
            }
        })
    });

    $('#choose_share_group').change(function () {
        var submitBtn = $(this).parent().find(".submit-btn").removeClass("disabled").removeAttr("disabled");
        var hasContext = $(this).find(":selected").data("has-context");

        if (hasContext == "1") {
            submitBtn.removeClass("btn-primary").addClass("btn-danger").val("Remove context from group");
        } else {
            submitBtn.removeClass("btn-danger").addClass("btn-primary").val("Add context to group");
        }
    });

    $('#choose_object_column').change(function () {
        $(this).parent().find(".submit-btn").removeClass("disabled").removeAttr("disabled");
    });

    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    });

    $(".scrollable-container").perfectScrollbar({
        wheelSpeed: 0.1
    });

    body.on("click", ".node-popup", function (event) {
        event.stopPropagation();
    }).on("click", function (event) {
        $(".node-popup").remove();
    });

    $(".collision-detection-btn").click(function () {
        conceptLattice.settings.collisionDetection = !conceptLattice.settings.collisionDetection;
        conceptLattice.force.resume();
    });

    $(".show-top-labels-btn").click(function () {
        if (conceptLattice.settings.showTopLabels) {
            conceptLattice.topLabels.style("visibility", "hidden");
        } else {
            conceptLattice.topLabels.style("visibility", "visible");
        }

        conceptLattice.settings.showTopLabels = !conceptLattice.settings.showTopLabels;
    });

    $(".show-bottom-labels-btn").click(function () {
        if (conceptLattice.settings.showBottomLabels) {
            conceptLattice.bottomLabels.style("visibility", "hidden");
        } else {
            conceptLattice.bottomLabels.style("visibility", "visible");
        }

        conceptLattice.settings.showBottomLabels = !conceptLattice.settings.showBottomLabels;
    });

    $(".collapse-labels-btn").click(function () {
        conceptLattice.settings.collapseLabels = !conceptLattice.settings.collapseLabels;

        conceptLattice.bottomLabels.text(function (d) {
            if (conceptLattice.settings.collapseLabels) {
                return d.ownedAttributes.join(" | ");
            } else {
                return d.attributes.join(" | ");
            }
        });

        conceptLattice.topLabels.text(function (d) {
            if (conceptLattice.settings.collapseLabels) {
                return d.ownedObjects.join(" | ");
            } else {
                return d.objects.join(" | ");
            }
        });
    });

    container.on("click", ".dimensions-lock-list .btn:not(.btn-primary)", function (event) {
        event.preventDefault();

        if ($(this).hasClass("btn-success")) {
            $(this).removeClass("btn-success");
            $(this).addClass("btn-default");
        } else {
            $(this).removeClass("btn-default");
            $(this).addClass("btn-success");

            var group = $(this).closest(".btn-group").data("group");
            $(this).closest(".dimensions-lock-list").find(".btn-group").each(function () {
                if ($(this).data("group") != group) {
                    var btn = $(this).find(".btn");

                    btn.removeClass("btn-success");
                    btn.addClass("btn-default");
                }
            })
        }
    });

    container.on("click", ".apply-dimensions-lock", function (event) {
        event.preventDefault();

        var activeItems = $(this).closest(".dimensions-lock-list").find(".btn-success");

        var lockType = activeItems.first().closest(".btn-group").data("group");
        var data = {
            'lock': []
        };

        activeItems.each(function () {
            var value = $(this).data("value");

            data['lock'].push(value);
        });

        var isLockable = searchForArray(data['lock'], LOCKABLE_ELEMENTS);

        if ((typeof LOCKABLE_ELEMENTS == 'undefined') || LOCKABLE_ELEMENTS.length == 0 || isLockable != -1) {
            var url = $(".main-lock-btn").attr("href").replace("_lockType_", lockType) + "?" + $.param(data);

            redirect(url);
        } else {
            alert("The elements you are trying to lock on are not part of any concept.");
        }
    });

    $(".create-context-table").scroll(adjustTablesScroll);

    $(".create-context-form")
        .on("click", ".data-cell", function (event) {
            event.preventDefault();

            if ($(this).text() == "X") {
                $(this).html("&nbsp;");
            } else {
                $(this).text("X");
            }
        })
        .on("change", ".add-object-cell input", function () {
            var name = $(this).val();
            $(this).val("");

            if (name == "") return;

            var input = $("<input>");
            input.attr("type", "text")
                .addClass("item-name-input")
                .val(name);

            var firstColumn = $("<td>");
            firstColumn.addClass("left-head-cell");
            firstColumn.append(input);
            firstColumn.css({
                "left": "-1px"
            });

            var newRow = $("<tr>");
            newRow.append(firstColumn);

            for (var i = 2; i < $(this).closest("tr").find("td").length; i++) {
                newRow.append($("<td>").addClass("data-cell").html("&nbsp;"));
            }

            newRow.append($("<td>").html("&nbsp;"));

            var tablesContainer = $(this).closest(".relation-tables");
            tablesContainer.find(".create-context-table").each(function () {
                $(this).find("tr:last").before(newRow.clone());
            });
        })
        .on("change", ".left-head-cell:not(.add-object-cell) input", function () {
            var val = $(this).val();
            var index = $(this).closest("tr").index();

            var tablesContainer = $(this).closest(".create-context-form").find(".relation-tables");
            tablesContainer.find(".create-context-table").each(function () {
                $(this).find("tbody tr").eq(index).find(".left-head-cell input").val(val);
            });
        })
        .on("change", ".add-attribute-cell input", function () {
            var name = $(this).val();
            $(this).val("");

            if (name == "") return;

            var input = $("<input>");
            input.attr("type", "text")
                .addClass("item-name-input")
                .val(name);

            var firstCell = $("<td>");
            firstCell.addClass("top-head-cell");
            firstCell.append(input);

            var cell = $("<td>").html("&nbsp;");
            var dataCell = cell.clone().addClass("data-cell");

            var tablesContainer = $(this).closest(".relation-tables");
            tablesContainer.find(".create-context-table").each(function () {
                $(this).find(".first-row .add-attribute-cell").before(firstCell.clone());

                var rows = $(this).find("tbody tr:not(:first)");
                var nrRows = rows.length;

                rows.each(function (i) {
                    if (i < nrRows - 1) {
                        $(this).find("td:last").before(dataCell.clone());
                    } else {
                        $(this).find("td:last").before(cell.clone());
                    }
                });
            });
        })
        .on("change", ".top-head-cell:not(.add-attribute-cell) input", function () {
            var val = $(this).val();
            var index = $(this).closest(".top-head-cell").index() - 1;

            var tablesContainer = $(this).closest(".create-context-form").find(".relation-tables");
            tablesContainer.find(".create-context-table").each(function () {
                $(this).find(".top-head-cell").eq(index).find("input").val(val);
            });
        })
        .on("click", ".btn-create-context", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            if (form.find("input[name=name]").val() == "") {
                showAlert("The name of the context cannot be empty.");
                return;
            }

            var dimCount = form.find('input[type=radio][name=context_type]:checked').val() == "dyadic" ? 2 : 3;
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

            if (dimCount == 3) {
                tablesContainer.find(".create-context-table").each(function () {
                    var val = $.trim($(this).find(".empty-cell input").val());
                    var input = $("<input>").attr("type", "hidden").attr("name", "conditions[]").val(val);

                    form.append(input);
                });
            }

            if (dimCount == 2) {
                var firstRow = table.find(".first-row");
                table.find("tr:not(.first-row)").each(function () {
                    var row = $(this);
                    var objectName = row.find(".left-head-cell input").val();

                    row.find(".data-cell").each(function (i) {
                        if ($.trim($(this).text()) == "X") {
                            var attributeCell = firstRow.find(".top-head-cell").eq(i);
                            var attributeName = attributeCell.find("input").val();

                            var val = objectName + "###" + attributeName;
                            var input = $("<input>").attr("type", "hidden").attr("name", "relation_tuples[]").val(val);
                            form.append(input);
                        }
                    });
                });
            } else {
                tablesContainer.find(".create-context-table").each(function () {
                    var table = $(this);
                    var conditionName = table.find(".empty-cell input").val();

                    var firstRow = table.find(".first-row");
                    table.find("tr:not(.first-row)").each(function () {
                        var row = $(this);
                        var objectName = row.find(".left-head-cell input").val();

                        row.find(".data-cell").each(function (i) {
                            if ($.trim($(this).text()) == "X") {
                                var attributeCell = firstRow.find(".top-head-cell").eq(i);
                                var attributeName = attributeCell.find("input").val();

                                var val = objectName + "###" + attributeName + "###" + conditionName;
                                var input = $("<input>").attr("type", "hidden").attr("name", "relation_tuples[]").val(val);
                                form.append(input);
                            }
                        });
                    });
                });
            }

            form.submit();
        })
        .on("click", ".btn-create-custom-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");
            var tablesContainer = form.find(".relation-tables");
            var table = tablesContainer.find(".create-context-table:first");

            var databaseConnection = $.trim($("#databaseConnection").val());
            $("<input>").attr("type", "hidden").attr("name", "databaseConnectionId").val(databaseConnection).appendTo(form);
            var tableName = $.trim($("#tableName").val());
            $("<input>").attr("type", "hidden").attr("name", "tableName").val(tableName).appendTo(form);
            var scaleName = $.trim($("#scaleName").val());
            $("<input>").attr("type", "hidden").attr("name", "scaleName").val(scaleName).appendTo(form);
            var scaleType = $.trim($("#scale-type-select-box").val());
            $("<input>").attr("type", "hidden").attr("name", "scaleType").val(scaleType).appendTo(form);

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
                    if ($.trim($(this).text()) == "X") {
                        var attributeCell = firstRow.find(".top-head-cell").eq(i);
                        var attributeName = attributeCell.find("input").val();

                        var val = objectName + "###" + attributeName;
                        var input = $("<input>").attr("type", "hidden").attr("name", "relation_tuples[]").val(val);
                        form.append(input);
                    }
                });
            });

            form.submit();
        })
        .on("click", ".btn-create-nominal-scale", function (event) {
            event.preventDefault();

            var form = $(this).closest("form");

            var databaseConnection = $.trim($("#databaseConnection").val());
            $("<input>").attr("type", "hidden").attr("name", "databaseConnectionId").val(databaseConnection).appendTo(form);
            var tableName = $.trim($("#tableName").val());
            $("<input>").attr("type", "hidden").attr("name", "tableName").val(tableName).appendTo(form);
            var scaleName = $.trim($("#scaleName").val());
            $("<input>").attr("type", "hidden").attr("name", "scaleName").val(scaleName).appendTo(form);
            var scaleType = $.trim($("#scale-type-select-box").val());
            $("<input>").attr("type", "hidden").attr("name", "scaleType").val(scaleType).appendTo(form);

            form.submit();
        })
        .on("click", ".btn-add-condition", function (event) {
            event.preventDefault();

            var tablesContainer = $(this).closest(".create-context-form").find(".relation-tables");
            var table = tablesContainer.find(".table-data:first").clone();
            table.find(".data-cell").html("&nbsp;");
            table.find(".empty-cell input").val("");
            table.scroll(adjustTablesScroll);

            tablesContainer.append(table);
        })
        .on("change", "input.subType", function () {
            if ($(this).val() === "simple") {
                $(".nominal-scale-elements-selector").hide();
            } else {
                $(".nominal-scale-elements-selector").show();

                if (tableData === null) {
                    var url = $(".create-new-scale-page").data("get-table-data-url");
                    var databaseConnection = $.trim($("#databaseConnection").val());
                    var tableName = $.trim($("#tableName").val());
                    $.ajax(url, {
                        method: "get",
                        data: {
                            'databaseConnectionId': databaseConnection,
                            'table': tableName
                        },
                        success: function(response) {
                            if (response.success) {
                                tableData = response.data.tableData;

                                var column = $("#column").val();
                                var values = [];
                                for (index in tableData.data) {
                                    var value = tableData.data[index][column];
                                    if (!(values.includes(value))) {
                                        values.push(value);
                                        $("<option value='" + value + "'>" + value + "</option>").appendTo($("#nominalScaleValues"));
                                    }
                                }

                            }
                        }
                    });
                }

            }
        });

    $('input[type=radio][name=context_type]').on('change', function () {
        var btnAddCondition = $(".btn-add-condition");
        var conditionInputs = $(".condition-input");
        var additionalTables = $(".create-context-form .table-data:not(:first)");

        switch ($(this).val()) {
            case 'dyadic':
                btnAddCondition.hide();
                conditionInputs.hide();
                additionalTables.hide();
                break;
            case 'triadic':
                btnAddCondition.show();
                conditionInputs.show();
                additionalTables.show();
                break;
        }
    });

    $(".concept-lattice-settings").on('change', "#choose_complex", function () {
        conceptLattice.settings.analogicalComplexId = parseInt($(this).val());
        conceptLattice.force.resume();
    })
});

function adjustTablesScroll() {
    var top = $(this).scrollTop() - 1;
    var left = $(this).scrollLeft() - 1;

    $(".create-context-table")
        .scrollTop($(this).scrollTop())
        .scrollLeft($(this).scrollLeft());

    $('.first-row').css({
        "top": top + "px"
    });

    $(".left-head-cell").css({
        "left": left + "px"
    });
}

function getConceptLatticeFromServer(url) {
    $.ajax({
        "url": url,
        success: function (response) {
            drawGraph(response);
        }
    });
}

function searchForArray(needle, haystack) {
    var i, j, current;

    for (i = 0; i < haystack.length; ++i) {
        if (needle.length === haystack[i].length) {
            current = haystack[i];
            for (j = 0; j < needle.length && needle[j] === current[j]; ++j);
            if (j === needle.length)
                return i;
        }
    }

    return -1;
}

function redirect(url) {
    window.location.href = url;
}

function adjustMinHeight(container) {
    var minHeight = Math.max($(window).height(), $("body").height()) - 160;

    container.css({
        'min-height': minHeight
    });
}

function showAlert(msg) {
    alert(msg);
}
