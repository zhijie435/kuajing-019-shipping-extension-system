<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">数据概览</h2>
    </div>

    <el-row :gutter="16" style="margin-bottom:16px">
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">承运商总数</div>
          <div class="stat-value">{{ stats.carrierTotal }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">已启用承运商</div>
          <div class="stat-value success">{{ stats.carrierEnabled }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">今日轨迹回传</div>
          <div class="stat-value">{{ stats.todayEvents }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">回调失败</div>
          <div class="stat-value danger">{{ stats.failedCallback }}</div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <el-col :span="12">
        <div class="card-wrapper">
          <h3 style="margin:0 0 16px;font-size:15px">轨迹状态分布</h3>
          <div ref="statusChartRef" style="height:300px"></div>
        </div>
      </el-col>
      <el-col :span="12">
        <div class="card-wrapper">
          <h3 style="margin:0 0 16px;font-size:15px">承运商轨迹量</h3>
          <div ref="carrierChartRef" style="height:300px"></div>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import * as echarts from 'echarts'
import request from '@/utils/request'

const statusChartRef = ref(null)
const carrierChartRef = ref(null)

const stats = reactive({
  carrierTotal: 0,
  carrierEnabled: 0,
  todayEvents: 0,
  failedCallback: 0,
})

const loadStats = async () => {
  try {
    const [carrierRes, trackingRes] = await Promise.all([
      request.get('/carriers', { params: { page_size: 1 } }),
      request.get('/tracking/stats'),
    ])

    stats.carrierTotal = carrierRes.pagination?.total || carrierRes.total || 0
    stats.failedCallback = trackingRes.failed_callback_count || 0

    const carrierDist = trackingRes.carrier_distribution || []
    const statusDist = trackingRes.status_distribution || {}

    let enabledCount = 0
    try {
      const allCarriers = await request.get('/carriers', { params: { status: 1, page_size: 1 } })
      stats.carrierEnabled = allCarriers.pagination?.total || allCarriers.total || 0
    } catch (e) { /* ignore */ }

    await nextTick()
    renderStatusChart(statusDist)
    renderCarrierChart(carrierDist)
  } catch (e) { /* ignore */ }
}

const statusLabels = {
  PICKED_UP: '已揽收',
  IN_TRANSIT: '运输中',
  OUT_FOR_DELIVERY: '派送中',
  DELIVERED: '已签收',
  EXCEPTION: '异常',
  UNKNOWN: '未知',
}

const renderStatusChart = (data) => {
  if (!statusChartRef.value) return
  const chart = echarts.init(statusChartRef.value)
  const items = Object.entries(data).map(([key, val]) => ({
    name: statusLabels[key] || key,
    value: val,
  }))
  chart.setOption({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
      type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: true,
      itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
      label: { show: true, formatter: '{b}: {c}' },
      data: items,
    }],
  })
}

const renderCarrierChart = (data) => {
  if (!carrierChartRef.value) return
  const chart = echarts.init(carrierChartRef.value)
  const names = data.map(d => d.carrier_code)
  const values = data.map(d => d.cnt)
  chart.setOption({
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: names },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar',
      data: values,
      itemStyle: { borderRadius: [4, 4, 0, 0], color: '#409eff' },
    }],
  })
}

onMounted(() => {
  loadStats()
})
</script>
