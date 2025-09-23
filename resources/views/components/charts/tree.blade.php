@props([
    'id' => 'treeChart',
    'title' => 'Tree Chart',
    'data' => [],
    'height' => 300,
    'orient' => 'TB',
    'color' => '#5470c6'
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            trigger: 'item',
            triggerOn: 'mousemove'
        },
        series: [{
            type: 'tree',
            data: @json($data),
            top: '10%',
            left: '20%',
            bottom: '10%',
            right: '20%',
            symbolSize: function (val) {
                return val * 2;
            },
            orient: '{{ $orient }}',
            expandAndCollapse: true,
            animationDuration: 550,
            animationDurationUpdate: 750,
            label: {
                show: true,
                position: 'left',
                verticalAlign: 'middle',
                align: 'right',
                fontSize: 12
            },
            leaves: {
                label: {
                    show: true,
                    position: 'right',
                    verticalAlign: 'middle',
                    align: 'left'
                }
            },
            emphasis: {
                focus: 'descendant'
            },
            itemStyle: {
                color: '{{ $color }}',
                borderColor: '#fff',
                borderWidth: 2
            },
            lineStyle: {
                color: '#ccc',
                width: 2,
                curveness: 0.3
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