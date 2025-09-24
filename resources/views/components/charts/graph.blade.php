@props([
    'id' => 'graphChart',
    'title' => 'Graph/Network Chart',
    'nodes' => [],
    'links' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de']
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var colors = @json($colors);

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {},
        legend: {
            data: ['Nodes'],
            top: 50
        },
        animationDuration: 1500,
        animationEasingUpdate: 'quinticInOut',
        series: [{
            name: 'Graph',
            type: 'graph',
            layout: 'force',
            data: @json($nodes).map(function(node, index) {
                return {
                    ...node,
                    itemStyle: {
                        color: colors[index % colors.length]
                    },
                    symbolSize: function (val) {
                        return Math.sqrt(val) * 2;
                    }
                };
            }),
            links: @json($links),
            categories: @json($nodes).map(function(node, index) {
                return {
                    name: node.category || 'Node ' + index,
                    itemStyle: {
                        color: colors[index % colors.length]
                    }
                };
            }),
            roam: true,
            focusNodeAdjacency: true,
            draggable: true,
            label: {
                show: true,
                position: 'right',
                formatter: '{b}'
            },
            lineStyle: {
                color: 'source',
                curveness: 0.3
            },
            emphasis: {
                focus: 'adjacency',
                lineStyle: {
                    width: 10
                }
            },
            force: {
                repulsion: 1000,
                edgeLength: [50, 200]
            }
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>