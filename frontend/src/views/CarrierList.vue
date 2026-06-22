<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">承运商管理</h2>
      <el-button type="primary" @click="handleCreate">
        <el-icon><Plus /></el-icon>新增承运商
      </el-button>
    </div>

    <div class="card-wrapper">
      <div class="filter-bar">
        <el-form :inline="true" :model="filterForm" @submit.prevent="loadList">
          <el-form-item label="关键词">
            <el-input v-model="filterForm.keyword" placeholder="编码/名称" clearable @clear="loadList" />
          </el-form-item>
          <el-form-item label="类型">
            <el-select v-model="filterForm.carrier_type" placeholder="全部" clearable @change="loadList">
              <el-option v-for="t in meta.carrier_types" :key="t.value" :label="t.label" :value="t.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="filterForm.status" placeholder="全部" clearable @change="loadList">
              <el-option v-for="s in meta.carrier_statuses" :key="s.value" :label="s.label" :value="s.value" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadList">查询</el-button>
            <el-button @click="resetFilter">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table :data="list" v-loading="loading" border stripe>
        <el-table-column prop="carrier_code" label="编码" width="130" />
        <el-table-column prop="carrier_name" label="名称" min-width="140" />
        <el-table-column prop="carrier_type" label="类型" width="80">
          <template #default="{ row }">
            {{ getMetaLabel(meta.carrier_types, row.carrier_type) }}
          </template>
        </el-table-column>
        <el-table-column prop="contact_name" label="联系人" width="90" />
        <el-table-column prop="country" label="国家" width="70" />
        <el-table-column prop="product_count" label="服务产品" width="90" align="center" />
        <el-table-column prop="priority" label="优先级" width="80" align="center" />
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">
              {{ getMetaLabel(meta.carrier_statuses, row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="protocol_type" label="协议" width="80" align="center">
          <template #default="{ row }">
            {{ row.protocol_type ? row.protocol_type.toUpperCase() : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="320" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleView(row)">详情</el-button>
            <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="warning" @click="handleLinkageCheck(row)">校验</el-button>
            <el-dropdown @command="(cmd) => handleStatusCmd(cmd, row)" style="margin-left:4px">
              <el-button size="small" :type="row.status === 1 ? 'warning' : 'success'">
                {{ row.status === 1 ? '停用' : '启用' }}
              </el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item v-if="row.status !== 1" command="1">启用</el-dropdown-item>
                  <el-dropdown-item v-if="row.status !== 2" command="2">停用</el-dropdown-item>
                  <el-dropdown-item v-if="row.status !== 3" command="3">测试中</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
            <el-button size="small" type="danger" @click="handleDelete(row)" style="margin-left:4px">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="total > 0"
        style="margin-top: 16px; justify-content: flex-end"
        background
        layout="total, sizes, prev, pager, next"
        :total="total"
        :page-size="pageSize"
        :current-page="page"
        :page-sizes="[10, 20, 50]"
        @size-change="(s) => { pageSize = s; loadList() }"
        @current-change="(p) => { page = p; loadList() }"
      />
    </div>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑承运商' : '新增承运商'" width="700px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="承运商编码" prop="carrier_code">
              <el-input v-model="form.carrier_code" :disabled="isEdit" placeholder="如 FEDEX, DHL" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="承运商名称" prop="carrier_name">
              <el-input v-model="form.carrier_name" placeholder="如 联邦快递" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="承运商类型" prop="carrier_type">
              <el-select v-model="form.carrier_type" placeholder="请选择">
                <el-option v-for="t in meta.carrier_types" :key="t.value" :label="t.label" :value="t.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所在国家" prop="country">
              <el-input v-model="form.country" placeholder="如 CN, US" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="联系人" prop="contact_name">
              <el-input v-model="form.contact_name" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="联系电话" prop="contact_phone">
              <el-input v-model="form.contact_phone" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="联系邮箱" prop="contact_email">
              <el-input v-model="form.contact_email" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="优先级" prop="priority">
              <el-input-number v-model="form.priority" :min="0" :max="999" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <el-select v-model="form.status">
                <el-option v-for="s in meta.carrier_statuses" :key="s.value" :label="s.label" :value="s.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="form.remark" type="textarea" :rows="2" />
        </el-form-item>

        <el-divider content-position="left">接入配置</el-divider>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="协议类型" prop="config.protocol_type">
              <el-select v-model="form.config.protocol_type" placeholder="请选择">
                <el-option v-for="t in meta.protocol_types" :key="t.value" :label="t.label" :value="t.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="认证方式" prop="config.auth_type">
              <el-select v-model="form.config.auth_type" placeholder="请选择">
                <el-option v-for="t in meta.auth_types" :key="t.value" :label="t.label" :value="t.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="API地址" prop="config.api_base_url">
          <el-input v-model="form.config.api_base_url" placeholder="https://api.example.com/v1" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="API Key" prop="config.api_key">
              <el-input v-model="form.config.api_key" show-password />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="API Secret" prop="config.api_secret">
              <el-input v-model="form.config.api_secret" show-password />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="回调地址" prop="config.callback_url">
          <el-input v-model="form.config.callback_url" placeholder="轨迹回传回调地址" />
        </el-form-item>
        <el-form-item label="回调密钥" prop="config.callback_secret">
          <el-input v-model="form.config.callback_secret" show-password />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="超时(秒)" prop="config.timeout_seconds">
              <el-input-number v-model="form.config.timeout_seconds" :min="5" :max="120" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="重试次数" prop="config.retry_times">
              <el-input-number v-model="form.config.retry_times" :min="0" :max="10" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="限频/分" prop="config.rate_limit">
              <el-input-number v-model="form.config.rate_limit" :min="1" :max="10000" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="linkageDialogVisible" title="联动校验结果" width="650px" destroy-on-close>
      <div v-if="linkageResult" v-loading="linkageLoading">
        <el-result
          :icon="linkageResult.all_passed ? 'success' : 'warning'"
          :title="linkageResult.all_passed ? '所有校验通过' : '校验未通过'"
          :sub-title="`承运商: ${linkageResult.carrier_name} (${linkageResult.carrier_code}) | ${linkageResult.passed_count}/${linkageResult.total_count} 项通过`"
        />
        <div style="margin-top:12px">
          <div v-for="check in linkageResult.checks" :key="check.name" style="margin-bottom:12px;padding:12px;border:1px solid #ebeef5;border-radius:4px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <el-icon :color="check.passed ? '#67c23a' : '#f56c6c'" size="18">
                <CircleCheckFilled v-if="check.passed" />
                <CircleCloseFilled v-else />
              </el-icon>
              <span :style="{fontWeight:600,fontSize:'14px',color:check.passed?'#67c23a':'#f56c6c'}">{{ check.name }}</span>
            </div>
            <div v-if="check.errors && check.errors.length" style="padding-left:26px">
              <div v-for="err in check.errors" :key="err" style="color:#f56c6c;font-size:13px;margin-bottom:2px">
                ✗ {{ err }}
              </div>
            </div>
            <div v-if="check.warnings && check.warnings.length" style="padding-left:26px">
              <div v-for="warn in check.warnings" :key="warn" style="color:#e6a23c;font-size:13px;margin-bottom:2px">
                ⚠ {{ warn }}
              </div>
            </div>
          </div>
        </div>
      </div>
      <el-empty v-else description="暂无校验结果" />
      <template #footer>
        <el-button @click="linkageDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CircleCheckFilled, CircleCloseFilled } from '@element-plus/icons-vue'
import request from '@/utils/request'

const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const dialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const formRef = ref(null)

const meta = reactive({
  carrier_types: [],
  carrier_statuses: [],
  protocol_types: [],
  auth_types: [],
})

const filterForm = reactive({
  keyword: '',
  carrier_type: '',
  status: '',
})

const defaultForm = () => ({
  carrier_code: '',
  carrier_name: '',
  carrier_type: 1,
  country: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  priority: 0,
  status: 0,
  remark: '',
  config: {
    protocol_type: 'http',
    api_base_url: '',
    auth_type: 'api_key',
    api_key: '',
    api_secret: '',
    auth_token: '',
    callback_url: '',
    callback_secret: '',
    timeout_seconds: 30,
    retry_times: 3,
    rate_limit: 100,
    extra_config: null,
    status: 1,
  },
})

const form = reactive(defaultForm())

const formRules = {
  carrier_code: [
    { required: true, message: '请输入承运商编码', trigger: 'blur' },
    { pattern: /^[A-Z0-9_]+$/, message: '编码仅支持大写字母、数字和下划线', trigger: 'blur' },
  ],
  carrier_name: [{ required: true, message: '请输入承运商名称', trigger: 'blur' }],
  carrier_type: [{ required: true, message: '请选择承运商类型', trigger: 'change' }],
  'config.api_base_url': [
    { type: 'url', message: '请输入合法URL', trigger: 'blur' },
  ],
  'config.callback_url': [
    { type: 'url', message: '请输入合法URL', trigger: 'blur' },
  ],
}

const getMetaLabel = (arr, val) => {
  const item = arr.find(i => i.value === val)
  return item ? item.label : String(val)
}

const statusTagType = (status) => {
  const map = { 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }
  return map[status] || 'info'
}

const loadMeta = async () => {
  try {
    const res = await request.get('/meta/all')
    Object.assign(meta, res)
  } catch (e) { /* ignore */ }
}

const loadList = async () => {
  loading.value = true
  try {
    const res = await request.get('/carriers', {
      params: { ...filterForm, page: page.value, page_size: pageSize.value },
    })
    list.value = res.items || []
    total.value = res.pagination ? res.pagination.total : (res.total || 0)
  } catch (e) { /* ignore */ }
  loading.value = false
}

const resetFilter = () => {
  filterForm.keyword = ''
  filterForm.carrier_type = ''
  filterForm.status = ''
  page.value = 1
  loadList()
}

const handleCreate = () => {
  isEdit.value = false
  editId.value = null
  Object.assign(form, defaultForm())
  dialogVisible.value = true
}

const handleEdit = async (row) => {
  isEdit.value = true
  editId.value = row.id
  try {
    const detail = await request.get(`/carriers/${row.id}`)
    Object.assign(form, {
      carrier_code: detail.carrier_code,
      carrier_name: detail.carrier_name,
      carrier_type: detail.carrier_type,
      country: detail.country,
      contact_name: detail.contact_name,
      contact_phone: detail.contact_phone,
      contact_email: detail.contact_email,
      priority: detail.priority,
      status: detail.status,
      remark: detail.remark,
      config: {
        protocol_type: detail.protocol_type || 'http',
        api_base_url: detail.api_base_url || '',
        auth_type: detail.auth_type || 'api_key',
        api_key: detail.api_key || '',
        api_secret: detail.api_secret || '',
        auth_token: detail.auth_token || '',
        callback_url: detail.callback_url || '',
        callback_secret: detail.callback_secret || '',
        timeout_seconds: detail.timeout_seconds || 30,
        retry_times: detail.retry_times || 3,
        rate_limit: detail.rate_limit || 100,
        extra_config: detail.extra_config || null,
        status: detail.config_status ?? 1,
      },
    })
    dialogVisible.value = true
  } catch (e) { /* ignore */ }
}

const handleSave = async () => {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) {
    ElMessage.warning('请修正表单中的错误项后再提交')
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await request.put(`/carriers/${editId.value}`, form)
      ElMessage.success('承运商更新成功')
    } else {
      await request.post('/carriers', form)
      ElMessage.success('承运商创建成功')
    }
    dialogVisible.value = false
    await loadList()
  } catch (e) {
    ElMessage.error(isEdit.value ? '承运商更新失败' : '承运商创建失败')
  }
  saving.value = false
}

const handleStatusCmd = async (cmd, row) => {
  try {
    await request.post('/carriers/status', { id: row.id, status: parseInt(cmd) })
    ElMessage.success('状态更新成功')
    await loadList()
  } catch (e) {
    ElMessage.error('状态更新失败')
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除承运商「${row.carrier_name}」？删除后不可恢复`, '确认删除', {
      type: 'warning',
      confirmButtonText: '确定删除',
      cancelButtonText: '取消',
    })
    await request.delete(`/carriers/${row.id}`)
    ElMessage.success('删除成功')
    await loadList()
  } catch (e) { /* cancel */ }
}

const handleView = (row) => {
  router.push(`/carriers/${row.id}`)
}

const linkageDialogVisible = ref(false)
const linkageLoading = ref(false)
const linkageResult = ref(null)

const handleLinkageCheck = async (row) => {
  linkageDialogVisible.value = true
  linkageLoading.value = true
  linkageResult.value = null
  try {
    linkageResult.value = await request.get(`/carriers/linkage-check/${row.id}`)
  } catch (e) {
    ElMessage.error('联动校验失败')
  }
  linkageLoading.value = false
}

onMounted(() => {
  loadMeta()
  loadList()
})
</script>
