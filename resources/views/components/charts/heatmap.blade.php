@props([
    'id' => 'heatmapChart',
    'title' => 'Heatmap Chart',
    'xAxis' => [],
    'yAxis' => [],
    'data' => [],
    'height' => 300,
    'min' => 0,
    'max' => 100
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));

    // Convert 2D array to ECharts format [x, y, value]
    var heatmapData = [];
    @json($data).forEach(function(row, yIndex) {
        row.forEach(function(value, xIndex) {
            heatmapData.push([xIndex, yIndex, value]);
        });
    });

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            position: 'top',
            formatter: function (params) {
                return params.seriesName + '<br/>' +
                       '{{ $xAxisLabel ?? "X" }}: ' + params.data[0] + '<br/>' +
                       '{{ $yAxisLabel ?? "Y" }}: ' + params.data[1] + '<br/>' +
                       'Value: ' + params.data[2];
            }
        },
        grid: {
            height: '70%',
            top: '15%'
        },
        xAxis: {
            type: 'category',
            data: @json($xAxis),
            splitArea: {
                show: true
            },
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'category',
            data: @json($yAxis),
            splitArea: {
                show: true
            }
        },
        visualMap: {
            min: {{ $min }},
            max: {{ $max }},
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: '5%',
            inRange: {
                color: ['#313695', '#4575b4', '#74add1', '#abd9e9', '#e0f3f8', '#ffffbf', '#fee090', '#fdae61', '#f46d43', '#d73027', '#a50026']
            }
        },
        series: [{
            name: 'Heatmap',
            type: 'heatmap',
            data: heatmapData,
            label: {
                show: false
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
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