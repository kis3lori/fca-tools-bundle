$.extend(true, conceptLattice, {});

function collide(node) {
    var textLength = 0;
    if (conceptLattice.settings.collapseLabels && conceptLattice.settings.showLabels) {
        textLength = Math.max(conceptLattice.bottomLabels[0][node.index].getComputedTextLength(),
            conceptLattice.topLabels[0][node.index].getComputedTextLength());
    }

    var nodeRadius = Math.max(15, textLength / 2) + 7;
    var nx1 = node.x - nodeRadius;
    var nx2 = node.x + nodeRadius;

    return function (quad, x1, y1, x2, y2) {
        if (quad.point && (quad.point !== node)) {
            var x = node.x - quad.point.x;
            var y = node.initialY - quad.point.initialY;
            var distanceBetweenNodes = Math.sqrt(x * x + y * y);
            var quadTextLength = 0;
            if (conceptLattice.settings.collapseLabels && conceptLattice.settings.showLabels) {
                quadTextLength = Math.max(conceptLattice.bottomLabels[0][quad.point.index].getComputedTextLength(),
                    conceptLattice.topLabels[0][quad.point.index].getComputedTextLength());
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
        .attr("class", "my-svg")
        .on("mousedown", function () {
            if (!conceptLattice.conceptClicked) {
                conceptLattice.links
                    .style("stroke-width", "1px");

                conceptLattice.nodes
                    .attr("r", conceptLattice.settings.circleRadius)
                    .style("fill", function (d) {
                        return ramp(d.level);
                    });

                conceptLattice.force.resume();
            }
        })
        ;

    conceptLattice.force
        .nodes(graph.nodes)
        .links(graph.links)
        .start();

    conceptLattice.links = svg.selectAll(".link")
        .data(graph.links)
        .enter().append("line")
        .attr("class", "link")
    ;

    conceptLattice.mouseMove = 0;
    conceptLattice.gnodes = svg.selectAll('g.gnode')
        .data(graph.nodes)
        .enter()
        .append('g')
        .classed('gnode', true)
        .on("mouseover", function () {
            if (!conceptLattice.settings.showLabels) {
                return d3.select(this).selectAll("text").style("visibility", "visible");
            }
        })
        .on("mouseout", function () {
            if (!conceptLattice.settings.showLabels) {
                return d3.select(this).selectAll("text").style("visibility", "hidden");
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
                        return ramp(d.level);
                    });

                d3.select(this).select("circle")
                    .attr("r", conceptLattice.settings.circleRadius)
                    .style("fill", "#EB9316");
            }

            conceptLattice.force.resume();
        })
        .call(conceptLattice.force.drag)
    ;

    var ramp = d3.scale.linear().domain([0, lastNode.level]).range(["white", "blue"]);

    conceptLattice.nodes = conceptLattice.gnodes.append("circle")
        .attr("class", "node")
        .attr("r", conceptLattice.settings.circleRadius)
        .style("fill", function (d) {
            return ramp(d.level);
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

    if (!conceptLattice.settings.showLabels) {
        conceptLattice.bottomLabels.style("visibility", "hidden");
        conceptLattice.topLabels.style("visibility", "hidden");
    }

    conceptLattice.force.on("tick", function () {
        var nodes = graph.nodes;

        if (conceptLattice.settings.collisionDetection) {
            var q = d3.geom.quadtree(nodes);
            var i = 0;
            var n = nodes.length;

            while (++i < n) q.visit(collide(nodes[i]));
        }

        conceptLattice.links.attr("x1", function (d) {
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
}
