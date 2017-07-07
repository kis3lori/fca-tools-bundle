$(document).ready(function () {
    var body = $("body");
    var container = $(".main-container");

    $(".elements-selector-wrapper").on("click", ".three-way-option:not(.disabled)", function () {
        showLoadingOverlay($(this), function (currentInstance) {
            var parent = currentInstance.closest(".three-way-button");
            var newLeft = currentInstance.position().left + 'px';
            parent.find(".slider").css("left", newLeft);
            parent.find(".three-way-option").addClass("disabled");

            var input = currentInstance.find("input");
            var form = currentInstance.closest("form");

            var data = {
                'constraint': {
                    'dimension': parent.data("dimension"),
                    'index': parent.data("index"),
                    'state': input.val()
                }
            };

            if (CONCEPT_FINDER_DATA['bookmarkId']) {
                data.reset = true;
                data.bookmarkId = CONCEPT_FINDER_DATA['bookmarkId'];
                CONCEPT_FINDER_DATA['bookmarkId'] = false;
            }

            if (CONCEPT_FINDER_DATA['activeState'] != CONCEPT_FINDER_DATA['searchContext']['history'].length - 1) {
                data.reset = true;
                data.activeState = CONCEPT_FINDER_DATA['activeState'];
                findConceptRemoveExtraHistory(data.activeState);
            }

            $.ajax({
                url: form.data("ajax-url"),
                method: "post",
                data: data,
                success: function (result) {
                    if (result.status == "success") {
                        findConceptUpdateHistory(data['constraint']);
                        updateState(result.data);
                        CONCEPT_FINDER_DATA['activeState'] = parseInt(CONCEPT_FINDER_DATA['activeState']) + 1;
                        CONCEPT_FINDER_DATA['searchContext']['history'] = CONCEPT_FINDER_DATA['searchContext']['history']
                            .slice(0, CONCEPT_FINDER_DATA['activeState']);
                        CONCEPT_FINDER_DATA['searchContext']['history'].push(data['constraint']);
                        CONCEPT_FINDER_DATA['searchContext']['states'] = CONCEPT_FINDER_DATA['searchContext']['states']
                            .slice(0, CONCEPT_FINDER_DATA['activeState']);
                        CONCEPT_FINDER_DATA['searchContext']['states'].push(result.data);
                    }

                    hideLoadingOverlay();
                }
            });
        });
    });

    $(".reset-concept-finder").on("click", function () {
        showLoadingOverlay($(this), function (currentInstance) {
            var url = currentInstance.data("url");

            $.ajax({
                url: url,
                method: "post",
                data: {
                    nrDim: CONCEPT_FINDER_DATA['dimensions'].length
                },
                success: function (result) {
                    if (result.status == "success") {
                        resetHistory();
                        CONCEPT_FINDER_DATA['searchContext']['history'] = [];
                        CONCEPT_FINDER_DATA['searchContext']['states'] = [];
                        updateState(result.data);
                    }

                    hideLoadingOverlay();
                }
            })
        });
    });

    $(".operation-history")
        .on("mouseover", ".operation", function (event) {
            event.preventDefault();
            $(this).addClass("btn-group-hover");
            var operation = $(this).closest(".operation");

            addOperationTooltip(operation);
        })
        .on("mouseout", ".operation", function (event) {
            event.preventDefault();
            $(this).removeClass("btn-group-hover");
            $(".operation-tooltip").remove();
        })
        .on("mousedown", ".operation", function (event) {
            event.preventDefault();
            $(this).addClass("btn-group-active");
        })
        .on("mouseup", ".operation", function (event) {
            event.preventDefault();
            showLoadingOverlay($(this), function (currentInstance) {
                currentInstance.removeClass("btn-group-active");

                var index = parseInt(currentInstance.index()) / 2;
                var state = CONCEPT_FINDER_DATA['searchContext']['states'][index];
                CONCEPT_FINDER_DATA['activeState'] = index;

                updateState(state);
                hideLoadingOverlay();
            });
        });

    body
        .on("mouseover", ".bookmark-item", function (event) {
            event.preventDefault();
            $(this).addClass("item-hover");
        })
        .on("mouseout", ".bookmark-item", function (event) {
            event.preventDefault();
            $(this).removeClass("item-hover");
        })
        .on("mouseover", ".bookmark-item span.btn", function (event) {
            event.preventDefault();

            var operation = $(this).closest(".operation");
            addOperationTooltip(operation);
        })
        .on("mouseout", ".bookmark-item span.btn", function (event) {
            event.preventDefault();
            $(".operation-tooltip").remove();
        })
        .on("mouseup", ".bookmark-item", function (event) {
            event.preventDefault();
            $(".bookmark-item").removeClass("item-active");
            $(this).addClass("item-active");

            var index = parseInt($(this).index()) / 2;
            $(this).closest(".modal").data("bookmark-index", index);
        })
        .on("click", ".load-bookmark", function (event) {
            event.preventDefault();
            showLoadingOverlay($(this), function (currentInstance) {
                var index = parseInt(currentInstance.closest(".modal").data("bookmark-index"));

                loadBookmark(index);
                hideLoadingOverlay();
            });
        })
        .on("click", ".delete-bookmark", function (event) {
            event.preventDefault();
            showLoadingOverlay($(this), function (currentInstance) {
                var bookmarkElem = currentInstance;
                var id = currentInstance.data("id");
                var index = currentInstance.data("index");

                $.ajax({
                    url: $(".delete-bookmark-url").data("url"),
                    data: {
                        "id": id
                    },
                    success: function (result) {
                        if (result.status == "success") {
                            bookmarkElem.closest(".bookmark-item").remove();
                            CONCEPT_FINDER_DATA['searchContext']['bookmarks'] = result.data;
                        }

                        hideLoadingOverlay();
                    }
                });
            });
        });

    $(".save-concept-finder-bookmark").click(function (event) {
        event.preventDefault();

        showLoadingOverlay($(this), function (currentInstance) {
            var data = {
                "name": "test"
            };

            if (CONCEPT_FINDER_DATA['bookmarkId']) {
                data.reset = true;
                data.bookmarkId = CONCEPT_FINDER_DATA['bookmarkId'];
                CONCEPT_FINDER_DATA['bookmarkId'] = false;
            }

            if (CONCEPT_FINDER_DATA['activeState'] != CONCEPT_FINDER_DATA['searchContext']['history'].length - 1) {
                data.reset = true;
                data.activeState = CONCEPT_FINDER_DATA['activeState'];
                findConceptRemoveExtraHistory(data.activeState);
            }

            $.ajax({
                url: currentInstance.data("url"),
                data: data,
                success: function (result) {
                    if (result.status == "success") {
                        CONCEPT_FINDER_DATA['searchContext']['bookmarks'] = result.data;
                    }

                    hideLoadingOverlay();
                }
            })
        });
    });

    $(".view-concept-finder-bookmarks").click(function (event) {
        event.preventDefault();
        showLoadingOverlay($(this), function (currentInstance) {
            $(".modal").remove();

            var bookmarksList = $("<div>").addClass("bookmarks-list");

            for (var bookmarkKey in CONCEPT_FINDER_DATA['searchContext']['bookmarks']) {
                var bookmark = CONCEPT_FINDER_DATA['searchContext']['bookmarks'][bookmarkKey];

                var list = $("<ul>");

                var first = true;
                for (var historyKey in bookmark["searchContext"]["history"]) {
                    var constraint = bookmark["searchContext"]["history"][historyKey];

                    if (!first) {
                        var arrow = $("<li>").addClass("arrow-right");
                        list.append(arrow);
                    }

                    var listItem = $("<li>").addClass("btn-group operation");
                    var span = $("<span>");
                    if (constraint['state'] == "in") {
                        span.addClass("btn btn-xs btn-success").text("In");
                    } else {
                        span.addClass("btn btn-xs btn-danger").text("Out");
                    }

                    var index = parseInt(constraint['index']);

                    var color = CONCEPT_FINDER_DATA["dimensionColorClasses"][constraint["dimension"]];
                    var secondSpan = $("<span>").addClass("btn btn-xs btn-" + color)
                        .attr("data-dimension", constraint["dimension"])
                        .attr("data-index", constraint["index"])
                        .text("#" + (index + 1));
                    listItem.append(span).append(secondSpan);
                    list.append(listItem);

                    first = false;
                }

                var deleteButton = $("<button>").addClass("btn btn-danger btn-xs pull-right delete-bookmark")
                    .attr("data-id", bookmark['id'])
                    .attr("data-index", bookmarkKey)
                    .text("Delete");

                var bookmarkItem = $("<div>").addClass("bookmark-item well well-sm")
                    .append(list)
                    .append(deleteButton);

                bookmarksList.append(bookmarkItem);

                var clearFix = $("<div>").addClass("clearfix");
                bookmarksList.append(clearFix);
            }

            // Create modal window
            var closeSpan = $("<span>").attr("aria-hidden", "true").html("&times;");
            var xButton = $("<button>").addClass("close")
                .attr("data-dismiss", "modal")
                .attr("type", "button")
                .attr("aria-label", "Close")
                .append(closeSpan);
            var title = $("<h4>").addClass("modal-title").text("Bookmarks");
            var modalHeader = $("<div>").addClass("modal-header")
                .append(xButton)
                .append(title);
            var modalBody = $("<div>").addClass("modal-body")
                .append(bookmarksList);
            var closeButton = $("<button>").addClass("btn btn-default")
                .attr("type", "button")
                .attr("data-dismiss", "modal")
                .text("Close");
            var loadButton = $("<button>").addClass("btn btn-primary load-bookmark")
                .attr("type", "button")
                .text("Load");
            var modalFooter = $("<div>").addClass("modal-footer")
                .append(closeButton)
                .append(loadButton);
            var modalContent = $("<div>").addClass("modal-content")
                .append(modalHeader)
                .append(modalBody)
                .append(modalFooter);
            var modalDialog = $("<div>").addClass("modal-dialog modal-lg")
                .append(modalContent);
            var modal = $("<div>").addClass("modal fade")
                .append(modalDialog);

            body.append(addSpaces(modal));
            $(modal).modal();

            hideLoadingOverlay();
        });
    });

    body
        .on("click", ".nav-to-prev-page:not(.disabled)", function (event) {
            event.preventDefault();
            showLoadingOverlay($(this), function (currentInstance) {
                var dimKey = currentInstance.closest("ul").data("dim");
                CONCEPT_FINDER_DATA['page'][dimKey] = CONCEPT_FINDER_DATA['page'][dimKey] - 1;

                handlePageUpdate(dimKey);
                hideLoadingOverlay();
            });
        })
        .on("click", ".nav-to-next-page:not(.disabled)", function (event) {
            event.preventDefault();
            showLoadingOverlay($(this), function (currentInstance) {
                var dimKey = currentInstance.closest("ul").data("dim");
                CONCEPT_FINDER_DATA['page'][dimKey] = CONCEPT_FINDER_DATA['page'][dimKey] + 1;

                handlePageUpdate(dimKey);
                hideLoadingOverlay();
            });
        })
        .on("click", ".nav-to-prev-page.disabled, .nav-to-next-page.disabled", function (event) {
            event.preventDefault();
        })
    ;

    $(".pager-input").on("change", function (event) {
        event.preventDefault();

        showLoadingOverlay($(this), function (currentInstance) {
            var dimKey = currentInstance.closest("ul").data("dim");
            CONCEPT_FINDER_DATA['page'][dimKey] = parseInt(currentInstance.val());

            handlePageUpdate(dimKey);
            hideLoadingOverlay();
        });
    });
});

function findConceptSetOption(option) {
    if (option.length == 0) return;

    var parent = option.closest(".three-way-button");

    var allOptions = parent.find(".three-way-option");
    allOptions.filter(".btn-default").addClass("disabled");
    allOptions.removeClass("active");
    option.filter(".btn-default").addClass("active")
        .find("input").attr("checked", "checked");

    if (option.data('state') != "") {
        allOptions.addClass('disabled');
    }

    var newLeft = option.position().left + 'px';
    parent.find(".slider").css("left", newLeft);
}

function findConceptResetOptions() {
    $(".three-way-option").removeClass("active").removeClass("disabled")
        .filter(".btn-default").addClass("active disabled")
        .find("input").attr("checked", "checked");

    $(".three-way-option.btn-default").each(function () {
        var parent = $(this).closest(".three-way-button");
        var newLeft = $(this).position().left + 'px';
        parent.find(".slider").css("left", newLeft);
    });
}

function findConceptUpdateHistory(constraint) {
    if (constraint['state'] == "") return;

    var index = parseInt(constraint['index']);

    var span1 = $("<span>")
        .addClass("btn btn-xs btn-" + (constraint['state'] == "in" ? "success" : "danger"))
        .text(constraint['state'][0].toUpperCase() + constraint['state'].slice(1));
    var span2 = $("<span>")
        .addClass("btn btn-xs btn-" + CONCEPT_FINDER_DATA['dimensionColorClasses'][constraint['dimension']])
        .attr("data-dimension", constraint['dimension'])
        .attr("data-index", index)
        .text("#" + (index + 1));

    var listItem = $("<li>").addClass("btn-group operation")
        .append(span1)
        .append(span2);
    var separator = $("<li>").addClass("arrow-right");

    var list = $(".operation-history ul");

    if (list.find("li").length != 0) {
        list.append(separator);
    }

    listItem = addSpaces(listItem);

    list.append(listItem);
}

function findConceptRemoveExtraHistory(index) {
    var double = index * 2;
    $(".operation-history ul li").each(function (i) {
        if (i > double) $(this).remove();
    });
}

function resetHistory() {
    $(".operation-history ul li").remove();
}

function addSpaces(element) {
    elementHtml = element.html().replace(/></g, '> <');
    element.html(elementHtml);

    return element;
}

function loadBookmark(index) {
    var bookmark = CONCEPT_FINDER_DATA['searchContext']['bookmarks'][index];
    var searchContext = bookmark['searchContext'];
    var bookmarks = CONCEPT_FINDER_DATA['searchContext']['bookmarks'];

    CONCEPT_FINDER_DATA['bookmarkId'] = bookmark["id"];
    CONCEPT_FINDER_DATA['searchContext'] = searchContext;
    CONCEPT_FINDER_DATA['searchContext']['bookmarks'] = bookmarks;

    var states = CONCEPT_FINDER_DATA['searchContext']['states'];
    if (states.length != 0) {
        var lastState = states[states.length - 1];
        updateState(lastState);
        resetHistory();

        for (historyKey in CONCEPT_FINDER_DATA['searchContext']['history']) {
            var history = CONCEPT_FINDER_DATA['searchContext']['history'][historyKey];

            findConceptUpdateHistory(history);
        }
    }
}

function handlePageUpdate(dimKey) {
    var page = CONCEPT_FINDER_DATA['page'][dimKey];
    var elementCount = CONCEPT_FINDER_DATA['contextDimensions'][dimKey].length;
    var pageSize = CONCEPT_FINDER_DATA['pageSize'];
    var nrPages = parseInt((elementCount - 1) / pageSize + 1);

    if (page < 1 || page > nrPages) {
        page = 1;
        CONCEPT_FINDER_DATA['page'][dimKey] = 1;
    }

    var list = $("ul.pager[data-dim=" + dimKey + "]");
    list.find("input").val(page);

    var navToPrevPage = list.find(".nav-to-prev-page");
    if (page <= 1) {
        navToPrevPage.addClass("disabled");
    } else {
        navToPrevPage.removeClass("disabled");
    }

    var navToNextPage = list.find(".nav-to-next-page");
    if (page >= nrPages) {
        navToNextPage.addClass("disabled");
    } else {
        navToNextPage.removeClass("disabled");
    }

    var offset = (page - 1) * pageSize;
    var limit = Math.min(page * pageSize, CONCEPT_FINDER_DATA['contextDimensions'][dimKey].length - 1);

    CONCEPT_FINDER_DATA['renderData'][dimKey]['offset'] = offset;
    CONCEPT_FINDER_DATA['renderData'][dimKey]['limit'] = limit;

    renderDim(dimKey);
}

function addOperationTooltip(operation) {
    var left = operation.position().left;
    var secondSpan = operation.find("span").eq(1);
    var dimension = secondSpan.data("dimension");
    var index = secondSpan.data("index");
    var color = CONCEPT_FINDER_DATA['dimensionColorClasses'][dimension];
    var element = CONCEPT_FINDER_DATA['contextDimensions'][dimension][index];

    var tooltip = $("<div>").addClass("operation-tooltip tooltip-" + color)
        .append($("<p>").text(element))
        .append($("<div>").addClass("arrow"));

    var parent = operation.closest("ul");
    parent.after(tooltip);

    var tooltipWidth = tooltip.width();
    var operationWidth = operation.width();
    var diff = (tooltipWidth - operationWidth) / 2;
    var finalLeftOffset = left - diff;

    tooltip.css({"left": finalLeftOffset + "px"});
}

function initConceptFinder() {
    CONCEPT_FINDER_DATA['bookmarkId'] = false;
    CONCEPT_FINDER_DATA['activeState'] = CONCEPT_FINDER_DATA['searchContext']['history'].length - 1;
    CONCEPT_FINDER_DATA['page'] = [];
    CONCEPT_FINDER_DATA['renderData'] = [];

    for (var dimKey = 0; dimKey < CONCEPT_FINDER_DATA["dimensions"].length; dimKey++) {
        CONCEPT_FINDER_DATA['page'][dimKey] = 1;
        CONCEPT_FINDER_DATA['renderData'][dimKey] = {
            'filter': [],
            'sort': [],
            'offset': 0,
            'limit': 0
        }
    }
}

function renderConceptFinder() {
    for (var dimKey = 0; dimKey < CONCEPT_FINDER_DATA["dimensions"].length; dimKey++) {
        renderDim(dimKey);
    }
}

function renderDim(dimKey) {
    var offset = CONCEPT_FINDER_DATA['renderData'][dimKey]['offset'];
    var limit = CONCEPT_FINDER_DATA['renderData'][dimKey]['limit'];
    if (limit == 0) {
        limit = Math.min(CONCEPT_FINDER_DATA['pageSize'], CONCEPT_FINDER_DATA['contextDimensions'][dimKey].length - 1);
    }

    var container = $(".elements-selector-wrapper .dim-" + dimKey + " .elements-container");
    container.find(".item-switch").remove();
    var dimensionName = CONCEPT_FINDER_DATA["dimensions"][dimKey];
    var itemSwitch, input;

    for (var index = offset; index <= limit; index++) {
        var element = CONCEPT_FINDER_DATA['contextDimensions'][dimKey][index];

        input = $("<input>").attr("type", "radio")
            .attr("name", dimensionName + "[" + index + "]")
            .attr("value", "out");
        var outLabel = $("<label>").addClass("btn btn-xs btn-danger three-way-option")
            .attr("data-state", "out")
            .text("Out")
            .prepend(input);
        input = $("<input>").attr("type", "radio")
            .attr("name", dimensionName + "[" + index + "]")
            .attr("value", "");
        var neutralLabel = $("<label>").addClass("btn btn-xs btn-default three-way-option disabled")
            .attr("data-state", "")
            .html("&nbsp;")
            .prepend(input);
        input = $("<input>").attr("type", "radio")
            .attr("name", dimensionName + "[" + index + "]")
            .attr("value", "in");
        var inLabel = $("<label>").addClass("btn btn-xs btn-success three-way-option")
            .attr("data-state", "in")
            .text("In")
            .prepend(input);

        var buttons = $("<div>").addClass("buttons btn-group btn-group-xs input-group")
            .append(outLabel)
            .append(neutralLabel)
            .append(inLabel);
        var slider = $("<div>").addClass("slider");
        var sliderTrack = $("<div>").addClass("slider-track");

        var threeWayButton = $("<div>").addClass("three-way-button pull-left")
            .attr("data-toggle", "buttons")
            .attr("data-dimension", dimKey)
            .attr("data-index", index)
            .append(buttons)
            .append(slider)
            .append(sliderTrack);
        var paragraph = $("<p>").text(element);
        var ribbon = $("<div>").addClass("ribbon")
            .append($("<span>").text("#" + (index + 1)));

        itemSwitch = $("<div>").addClass("item-switch well well-sm")
            .attr("data-index", index)
            .append(threeWayButton)
            .append(paragraph)
            .append(ribbon);

        container.append(itemSwitch);
    }

    var states = CONCEPT_FINDER_DATA['searchContext']['states'];

    if (states.length > 0) {
        var state = states[states.length - 1];

        updateState(state);
    }
}

function updateState(state) {
    if (typeof state == 'undefined') {
        state = {
            'status': "start",
            'constraints': [],
            'foundConcept': null
        }
    }

    var status = state['status'];
    $('.hint-message .message').removeClass("active")
        .filter(".state-" + status).addClass("active");

    findConceptResetOptions();

    for (var key in state['constraints']) {
        var constraint = state['constraints'][key];
        var selector = ".three-way-button[data-dimension='" + constraint['dimension'] + "'][data-index='" + constraint['index'] + "']";

        var option = $(selector).find(".three-way-option[data-state='" + constraint['state'] + "']");
        findConceptSetOption(option);
    }

    $(".found-concept").remove();
    if (state['foundConcept'] != null) {
        var concept = createConcept(state['foundConcept']);

        $(".find-concept-page form").after(concept);
    }
}

function createConcept(foundConcept) {
    var dimensions = CONCEPT_FINDER_DATA.dimensions;
    var item = $("<li>").addClass('list-group-item');

    for (var dimKey in dimensions) {
        var dimension = dimensions[dimKey];
        var dimName = dimension.charAt(0).toUpperCase() + dimension.slice(1);
        var itemName = $("<li>").text(dimName + ":");
        var inlineList = $("<ul>").addClass("list-inline");
        var itemKey, elemId, elemName, link;

        if (dimensions.length == 3) {
            var url = CONCEPT_FINDER_DATA.lockedConceptLatticeUrl;
            var lock = "";

            for (itemKey in foundConcept[dimKey]) {
                elemId = foundConcept[dimKey][itemKey];
                elemName = CONCEPT_FINDER_DATA.contextDimensions[dimKey][elemId];
                lock += "&lock[]=" + elemName;

                link = $("<li>").text(elemName);
                inlineList.append(link);
            }
            lock = lock.slice(1);
            var lockType = dimension.slice(0, dimension.length - 1);

            var inlineLockItem = $("<li>");
            var lockLink = $("<a>").addClass("btn btn-primary btn-xs")
                .attr("target", "_blank")
                .attr("href", url.replace("_lockType_", lockType) + "?" + lock)
                .text("Lock");
            inlineLockItem.prepend(lockLink);

            inlineList.prepend(inlineLockItem)
        } else {
            for (itemKey in foundConcept[dimKey]) {
                elemId = foundConcept[dimKey][itemKey];
                elemName = CONCEPT_FINDER_DATA.contextDimensions[dimKey][elemId];
                link = $("<li>").text(elemName);
                inlineList.append(link);
            }
        }

        inlineList.prepend(itemName);

        item.append(inlineList);
    }

    var list = $("<ul>").addClass('list-group')
        .append(item);
    var context = $("<div>")
        .addClass("found-concept concepts")
        .append(list);

    context = addSpaces(context);

    return context;
}
