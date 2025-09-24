@props([
    'id' => 'chart',
    'title' => '',
    'data' => [],
    'height' => 300,
    'colors' => ['#91cc75', '#fac858', '#ee6666', '#73c0de', '#5470c6']
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
            left: 'center'
        },
        tooltip: {
            trigger: 'item'
        },
        legend: {
            orient: 'vertical',
            left: 'left'
        },
        series: [{
            name: 'Data',
            type: 'pie',
            radius: '50%',
            data: @json($data).map(function(item, index) {
                var colors = @json($colors);
                return {
                    value: item.value,
                    name: item.name,
                    itemStyle: {color: colors[index % colors.length]}
                };
            }),
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
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