@props([
    'id' => 'scatterChart',
    'title' => 'Scatter Chart',
    'data' => [],
    'height' => 300,
    'xAxisLabel' => 'X Axis',
    'yAxisLabel' => 'Y Axis',
    'symbolSize' => 10,
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
            formatter: function (params) {
                return params.seriesName + '<br/>' +
                       '{{ $xAxisLabel }}: ' + params.data[0] + '<br/>' +
                       '{{ $yAxisLabel }}: ' + params.data[1];
            }
        },
        legend: {
            data: ['Data'],
            top: 50
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            top: '15%',
            containLabel: true
        },
        xAxis: {
            type: 'value',
            name: '{{ $xAxisLabel }}',
            nameLocation: 'middle',
            nameGap: 30,
            splitLine: {
                show: false
            }
        },
        yAxis: {
            type: 'value',
            name: '{{ $yAxisLabel }}',
            nameLocation: 'middle',
            nameGap: 40
        },
        series: [{
            name: 'Data',
            type: 'scatter',
            symbolSize: function (data) {
                return {{ $symbolSize }};
            },
            data: @json($data),
            itemStyle: {
                color: '{{ $color }}',
                shadowBlur: 10,
                shadowColor: 'rgba(0, 0, 0, 0.3)'
            },
            emphasis: {
                focus: 'series',
                itemStyle: {
                    borderColor: '#fff',
                    borderWidth: 2
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