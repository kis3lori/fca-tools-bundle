$.extend(true, conceptLattice, {});

function collide(node) {
    var textLength = 0;
    if (conceptLattice.settings.collapseLabels && conceptLattice.settings.showTopLabels) {
        textLength = Math.max(textLength, conceptLattice.topLabels[0][node.index].getComputedTextLength());
    }
    if (conceptLattice.settings.collapseLabels && conceptLattice.settings.showBottomLabels) {
        textLength = Math.max(conceptLattice.bottomLabels[0][node.index].getComputedTextLength(), textLength);
    }

    var nodeRadius = Math.max(15, textLength / 2) + 7;
    var nx1 = node.x - nodeRadius;
    var nx2 = node.x + nodeRadius;

    return function (quad, x1, y1, x2, y2) {
        if (quad.point && (quad.point !== node) && (quad.point.level == node.level)) {
            var x = node.x - quad.point.x;
            var y = node.initialY - quad.point.initialY;
            var distanceBetweenNodes = Math.sqrt(x * x + y * y);
            var quadTextLength = 0;
            if (conceptLattice.settings.collapseLabels && conceptLattice.settings.showTopLabels) {
                quadTextLength = Math.max(quadTextLength, conceptLattice.topLabels[0][quad.point.index].getComputedTextLength());
            }
            if (conceptLattice.settings.collapseLabels && conceptLattice.settings.showBottomLabels) {
                quadTextLength = Math.max(conceptLattice.bottomLabels[0][quad.point.index].getComputedTextLength(), quadTextLength);
            }

            var quadRadius = Math.max(15, quadTextLength / 2) + 7;
            var distanceBetweenColliders = nodeRadius + quadRadius;
        }

        if (distanceBetweenNodes > 0 && distanceBetweenColliders - distanceBetweenNodes > 0) {
            var lerpDistance = (distanceBetweenNodes - distanceBetweenColliders) / distanceBetweenNodes * .5;
            node.x -= x *= lerpDistance;
            quad.point.x += x;
        }

        return x1 > nx2 || x2 < nx1;
    };
}

function getNodeColor(node) {
    var graph = conceptLattice.graph;

    if (conceptLattice.settings.analogicalComplexId != -1) {
        var analogicalComplex = graph.analogicalComplexes[conceptLattice.settings.analogicalComplexId];
        if (node.index == analogicalComplex[0] ||
            node.index == analogicalComplex[2]) {
            return "red";
        }

        if (node.index == analogicalComplex[1] ||
            node.index == analogicalComplex[3]) {
            return "green";
        }
    }

    var ramp = d3.scale.linear().domain([0, graph.nodes[graph.lastNode].level]).range(["white", "blue"]);

    return ramp(node.level);
}

function drawGraph(graph) {
    conceptLattice.graph = graph;
    var lastNode = graph.nodes[graph.lastNode];
    var width = conceptLattice.container.width();
    var height = graph.maxLevel * 100;

    graph.nodes.forEach(function (node, index) {
        node.x = width / 2 - conceptLattice.settings.circleRadius + index;
        node.y = 50 + (node.level - 1) * 100;
        node.initialY = node.y;
        node.ownedObjects = node.objects;
        node.ownedAttributes = node.attributes;
    });

    graph.nodes[0].fixed = true;
    lastNode.fixed = true;

    graph.links.forEach(function (link, index) {
        var sourceNode = graph.nodes[link.source];
        var targetNode = graph.nodes[link.target];

        targetNode.ownedAttributes = targetNode.ownedAttributes.filter(function (x) {
            return sourceNode.attributes.indexOf(x) < 0;
        });
        sourceNode.ownedObjects = sourceNode.ownedObjects.filter(function (x) {
            return targetNode.objects.indexOf(x) < 0;
        });
    });

    conceptLattice.force = d3.layout.force()
        .charge(function (d, i) {
            return -240;
        })
        .linkDistance(function (l) {
            return Math.abs(l.source.level - l.target.level) * conceptLattice.settings.linkDistance - 20;
        })
        .size([width, height])
        .gravity(0)
    ;

    var svg = d3.select(".concept-lattice-container").append("svg")
        .attr("width", width)
        .attr("height", height)
        .style("font-family", '"Helvetica Neue", Helvetica, Arial, sans-serif')
        .style("font-size", "14px")
        .style("line-height", "1.42857143")
        .style("color", "#333")
        .style("background-color", "#FFF")
        .attr("class", "my-svg")
        .on("mousedown", function () {
            if (!conceptLattice.conceptClicked) {
                conceptLattice.links
                    .style("stroke-width", "1px");

                conceptLattice.nodes
                    .attr("r", conceptLattice.settings.circleRadius)
                    .style("fill", function (d) {
                        return getNodeColor(d);
                    });

                conceptLattice.force.resume();
            }
        });

    conceptLattice.force
        .nodes(graph.nodes)
        .links(graph.links)
        .start();

    conceptLattice.links = svg.selectAll(".link")
        .data(graph.links)
        .enter().append("line")
        .attr("class", "link")
        .style("stroke-width", "0.6")
        .style("stroke", "#999")
    ;

    conceptLattice.mouseMove = 0;
    conceptLattice.gnodes = svg.selectAll('g.gnode')
        .data(graph.nodes)
        .enter()
        .append('g')
        .classed('gnode', true)
        .on("mouseover", function () {
            if (!conceptLattice.settings.showTopLabels) {
                return d3.select(this).select("text").style("visibility", "visible");
            }
            if (!conceptLattice.settings.showBottomLabels) {
                return d3.select(d3.select(this).selectAll("text")[0][1]).style("visibility", "visible");
            }
        })
        .on("mouseout", function () {
            if (!conceptLattice.settings.showTopLabels) {
                return d3.select(this).select("text").style("visibility", "hidden");
            }
            if (!conceptLattice.settings.showBottomLabels) {
                return d3.select(d3.select(this).selectAll("text")[0][1]).style("visibility", "hidden");
            }
        })
        .on("dblclick", function (d, i) {
            d.fixed = false;
            conceptLattice.force.resume();
        })
        .on("mousemove", function (d, i) {
            conceptLattice.mouseMove += 1;

            if (conceptLattice.conceptClicked == true && conceptLattice.mouseMove > 15) {
                conceptLattice.conceptWasDragged = true;
            }
        })
        .on("mousedown", function (d, i) {
            conceptLattice.conceptClicked = true;
        })
        .on("mouseup", function (d, i) {
            conceptLattice.mouseMove = 0;
            conceptLattice.conceptClicked = false;
            if (conceptLattice.conceptWasDragged) {
                d.fixed = true;
                conceptLattice.conceptWasDragged = false;
            } else {
                var mainNode = d;
                var markedNodes = [];

                conceptLattice.links
                    .style("stroke-width", function (d, i) {
                        var diff1 = mainNode.attributes.filter(function (x) {
                                return d.source.attributes.indexOf(x) < 0;
                            }).length + mainNode.attributes.filter(function (x) {
                                return d.target.attributes.indexOf(x) < 0;
                            }).length;
                        var diff2 = mainNode.objects.filter(function (x) {
                                return d.source.objects.indexOf(x) < 0;
                            }).length + mainNode.objects.filter(function (x) {
                                return d.target.objects.indexOf(x) < 0;
                            }).length;

                        if (diff1 == 0 || diff2 == 0) {
                            d3.select(this).style("stroke", "#245580");
                            markedNodes.push(d.source.index);
                            markedNodes.push(d.target.index);

                            return "3px";
                        }

                        return "1px";
                    });

                conceptLattice.nodes
                    .attr("r", function (d, i) {
                        if (markedNodes.indexOf(d.index) >= 0) {
                            return conceptLattice.settings.circleRadius;
                        }

                        return conceptLattice.settings.circleRadius - (conceptLattice.settings.circleRadiusVariation);
                    })
                    .style("fill", function (d) {
                        return getNodeColor(d);
                    });

                d3.select(this).select("circle")
                    .attr("r", conceptLattice.settings.circleRadius)
                    .style("fill", "#EB9316");
            }

            conceptLattice.force.resume();
        })
        .call(conceptLattice.force.drag)
    ;

    conceptLattice.nodes = conceptLattice.gnodes.append("circle")
        .attr("class", "node")
        .attr("r", conceptLattice.settings.circleRadius)
        .style("stroke", "#FFF")
        .style("stroke-width", "1.5px")
        .style("fill", function (d) {
            return getNodeColor(d);
        });

    conceptLattice.topLabels = conceptLattice.gnodes.append("text")
        .attr("x", 0)
        .attr("dy", conceptLattice.settings.textTopOffset)
        .attr("text-anchor", "middle")
        .text(function (d) {
            if (conceptLattice.settings.collapseLabels) {
                return d.ownedAttributes.join(" | ");
            } else {
                return d.attributes.join(" | ");
            }
        });

    conceptLattice.bottomLabels = conceptLattice.gnodes.append("text")
        .attr("x", 0)
        .attr("dy", conceptLattice.settings.textBottomOffset)
        .attr("text-anchor", "middle")
        .text(function (d) {
            if (conceptLattice.settings.collapseLabels) {
                return d.ownedObjects.join(" | ");
            } else {
                return d.objects.join(" | ");
            }
        });

    if (!conceptLattice.settings.showTopLabels) {
        conceptLattice.topLabels.style("visibility", "hidden");
    }
    if (!conceptLattice.settings.showBottomLabels) {
        conceptLattice.bottomLabels.style("visibility", "hidden");
    }

    conceptLattice.force.on("tick", function () {
        var nodes = graph.nodes;

        if (conceptLattice.settings.collisionDetection) {
            var q = d3.geom.quadtree(nodes);
            var i = 0;
            var n = nodes.length;

            while (++i < n) q.visit(collide(nodes[i]));
        }

        conceptLattice.links
            .attr("x1", function (d) {
                return d.source.x;
            })
            .attr("y1", function (d) {
                return d.source.initialY;
            })
            .attr("x2", function (d) {
                return d.target.x;
            })
            .attr("y2", function (d) {
                return d.target.initialY;
            });

        conceptLattice.nodes
            .style("fill", function (d) {
                return getNodeColor(d);
            });

        // Translate the groups
        conceptLattice.gnodes.attr("transform", function (d) {
            return 'translate(' + [d.x, d.initialY] + ')';
        });
    });

    if (typeof LOCKABLE_ELEMENTS != 'undefined') {
        conceptLattice.gnodes.on("contextmenu", function (d, i) {
            d3.event.preventDefault();

            var triadicConcept = d.triadicConcept;

            d3.selectAll(".node-popup").remove();

            var parentOffset = $(".concept-lattice-container").offset();
            var relX = d3.event.pageX - parentOffset.left;
            var relY = d3.event.pageY - parentOffset.top;

            var popup = d3.select(".concept-lattice-container")
                .append("div")
                .attr("class", "node-popup")
                .style("left", relX + "px")
                .style("top", relY + "px");

            popup.append("h3").attr("class", "popup-title").text("Triadic Concept");
            var content = popup.append("div").attr("class", "popup-content");
            var lockList = content.append("div").attr("class", "dimensions-lock-list");

            var group, btnClass;
            var key, value;
            var dimensions = ["object", "attribute", "condition"];

            for (var dimKey = 0; dimKey < 3; dimKey++) {
                group = lockList.append("div")
                    .attr("class", "btn-group")
                    .attr("role", "group")
                    .attr("data-group", dimensions[dimKey])
                ;
                for (key in triadicConcept[dimKey]) {
                    value = triadicConcept[dimKey][key];
                    btnClass = "btn-default";

                    if (conceptLattice.graph.lock.indexOf(value) > -1) {
                        btnClass = "btn-success";
                    }

                    group.append("a").attr("class", "btn " + btnClass).attr("data-value", value).text(value);
                }
                lockList.append("br");
            }

            lockList.append("button").attr("class", "btn btn-primary apply-dimensions-lock").text("Lock");
        });
    }

    if (typeof graph.analogicalComplexes != 'undefined') {
        var select = $("#choose_complex");
        for (var index in graph.analogicalComplexes) {
            $("<option>").text(index).val(index).appendTo(select);
        }
    } else {
        $(".complex-selector").remove();
    }

    $(".printable-concept-lattice-btn").click(function () {
        var svgString = getSVGString(svg.node());
        svgString2Image(svgString, 8 * width, 8 * height);
    });
}

function getSVGString(svgNode) {
    svgNode.setAttribute('xlink', 'http://www.w3.org/2000/xlink');

    var serializer = new XMLSerializer();
    var svgString = serializer.serializeToString(svgNode);
    svgString = svgString.replace(/(\w+)?:?xlink=/g, 'xmlns:xlink='); // Fix root xlink without namespace
    svgString = svgString.replace(/NS\d+:href/g, 'xlink:href'); // Safari NS namespace fix

    return svgString;
}

function svgString2Image(svgString, width, height) {
    var imgsrc = 'data:image/svg+xml;base64,' + btoa(svgString);

    var canvas = document.createElement("canvas");
    var context = canvas.getContext("2d");
    canvas.width = width;
    canvas.height = height;

    var image = new Image();
    image.onload = function () {
        context.clearRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        canvas.toBlob(function (blob) {
            saveAs(blob, "ConceptLattice " + new Date().toLocaleString());
        });
    };

    image.src = imgsrc;
}