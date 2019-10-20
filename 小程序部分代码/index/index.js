var init = require('../../utils/init.js');
const app = getApp();
import ServerceApi from '../../utils/serverceApi.js';
import Http from '../../utils/http.js';
import until from "../../utils/util.js"
let _util = new until();
let _ServerceApi = new ServerceApi();
let _Http = new Http();
const dataUtil = require('../../utils/data.js')
var newInit = Object.assign({}, init)

newInit.data = Object.assign({}, newInit.data, {
  imageurl: app.globalData.imageurl,
  swiperCurrent: 0,

  indicatorDots: true,

  autoplay: true,

  interval: 3000,

  duration: 800,

  circular: true,

  links: [

    '../user/user',

    '../user/user',

    '../user/user'

  ],
  floorstatus: false,
  countDownList: [],
  actEndTimeList: [],
  time:"",


});
newInit = Object.assign({}, newInit, {
  // 获取滚动条当前位置
  onPageScroll: function(e) {
    console.log(e)
    if (e.scrollTop > 100) {
      this.setData({
        floorstatus: true
      });
    } else {
      this.setData({
        floorstatus: false
      });
    }
  },
  onUnload() {
   // debugger
    // this.setData({
    //   timer:null
    // })
    clearInterval(this.data.timer);
  },
  //获取首页接口信息
  getIndexPage() {
    _ServerceApi.getIndex().then(res => {
      console.log("返回结果", res)
      var arrList2 = [];
      var arrLists = [];
      var yxhdarrList2 = [];
      var yxhdarrLists = [];
      if (res.code == 0) {
        for (var i = 0; i < res.data.hot_goods_list.length;i++){
          if(i==0||i==1){
            arrList2.push(res.data.hot_goods_list[i])
          }else{
            arrLists.push(res.data.hot_goods_list[i])
          }
        }
        for (var i = 0; i < res.data.yxhd.length; i++) {
          if (i == 0 || i == 1) {
            yxhdarrList2.push(res.data.yxhd[i])
          } else {
            yxhdarrLists.push(res.data.yxhd[i])
          }
        }
        var arrArticle = []
        for (var i = 0; i < res.data.article.length; i++) {
          res.data.article[i].article_title=res.data.article[i].article_title.replace(/\s*/g, "")
          arrArticle[i] = res.data.article[i]
        }
        console.log(res.data.article)
        this.setData({
          imgUrls: res.data.adv_list,
          messages_state: res.data.messages_state,
          article: arrArticle,
          nav_list: res.data.nav_list,
          /**秒杀 */
          time: res.data.seconds_kill.length == 0? null : res.data.seconds_kill.time,
          state: res.data.seconds_kill.state,
          seconds_killList: res.data.seconds_kill.list,
          hot_goods_list2: arrList2,
          hot_goods_lists: arrLists,
          yxhd2: yxhdarrList2,
            yxhds: yxhdarrLists,
          good_class: res.data.good_class,
          tj_goods_list: res.data.tj_goods_list,
          model_state: res.data.model_state
          
        })
      

      }
     // this.init()
  
      if (this.data.time) {
       this.init()
      }else{
      

      }
    }, err => {
      _util.showToast('none', "发送失败")
    });

  },
  init: function() {
    var { time} = this.data
   // var time="0:0:5"
   var index0= time.indexOf(",")
    var index = time.indexOf(":")
    var index1 = time.lastIndexOf(":")
    var h = time.slice(index0+1, index);
    var m = time.slice(index + 1, index1);
    var s = time.slice(index1 + 1);
    var day = time.slice(0,index0)
    let obj = null;
    let countDownArr = []
    this.data.timer = setInterval(() => { //注意箭头函数！！
        --s;
      if (s < 0) {
        --m;
        s = 59;
      }
      if (m < 0) {
        --h;
        m = 59
      }
      if (h < 0) {
        s = 0;
        m = 0;
       // clearInterval(this.data.timer);
      }
         function checkTime(i) {
        if (i < 10) {
          i = '0' + i
        }
        return i;
      }

      obj = {
        s: checkTime(s),
        m: checkTime(m),
        h: checkTime(h),
        day: checkTime(day)
      }
      this.setData({
        s: obj.s,
        m: obj.m,
        h: obj.h,
        day: obj.day
      })

      if (this.data.s == "00" && this.data.m == "00" && this.data.h == "00") {
        clearInterval(this.data.timer);
        this.getIndexPage()
      
      }
    }, 1000);


  },


  //回到顶部
  goTop(e) { // 一键回到顶部
    console.log(wx.pageScrollTo)
    if (wx.pageScrollTo) {
      wx.pageScrollTo({
        scrollTop: 0
      })
    } else {
      wx.showModal({
        title: '提示',
        content: '当前微信版本过低，无法使用该功能，请升级到最新微信版本后重试。'
      })
    }
  },

  bindTopSlideChange(event) {
    const {
      current
    } = event.detail;
    console.log('huangfiefaksldh>>>>>>>>>>>>', current)
    this.setData({
      topSlideIndex: current
    });
  },

  //轮播图的切换事件

  swiperChange: function(e) {
    this.setData({

      swiperCurrent: e.detail.current

    })

  },

  //点击指示点切换

  chuangEvent: function(e) {

    this.setData({

      swiperCurrent: e.currentTarget.id

    })

  },

  //点击图片触发事件

  swipclick: function(e) {

    console.log(this.data.swiperCurrent);

    wx.switchTab({

      url: this.data.links[this.data.swiperCurrent]

    })

  },
  toMessage() {
    wx.navigateTo({
      url: '/pages/message/message',
    })
  },
  toSeatcher() {
    wx.navigateTo({
      url: '/pages/search/search',
    })
  },
  toXSQG() {
    clearInterval(this.data.timer);


    wx.navigateTo({
      url: '/pages/xsqg/xsqg',
    })
  },
  torxsp() {
    wx.navigateTo({
      url: '/pages/hotShoppingList/hotShoppingList',
    })
  },
/**营销活动id */
  toyxhdShopping(e) {
    console.log(e)
    var fid = e.currentTarget.dataset.fid
    wx.navigateTo({
      url: '/pages/shoppingList/shoppingList?fid=' + fid,
    })
  },
  /**商品详情 */
  toShoppingDetail(e) {
    console.log(e)
    var gid = e.currentTarget.dataset.gid
    wx.navigateTo({
      url: '/pages/shoppingDetailFl/shoppingDetailFl?gid=' + gid,
    })
  },
  /**
   * 页面相关事件处理函数--监听用户下拉动作
   */
  onPullDownRefresh: function () {
    clearInterval(this.data.timer);
    this.getIndexPage();
    setInterval(function () {
      wx.stopPullDownRefresh(); // 数据请求成功后，停止刷新
    }, 1000)
  },
  toGgDetail(e) {
    console.log(e)
    var gid = e.currentTarget.dataset.id
    wx.navigateTo({
      url: '/pages/ggMessageDetail/ggMessageDetail?gid=' + gid,
    })
  },
  toshoppingDetailfl(e){
    var gid = e.currentTarget.dataset.gid
    var type = e.currentTarget.dataset.type

    if (gid) {
      if (type==1){
        wx.navigateTo({
          url: '/pages/ztxq/ztxq?gid=' + gid,
        })
      }else{
        wx.navigateTo({
          url: '/pages/flshoppingList/flshoppingList?cid=' + gid,
        })
      }
    
    }
  
  },
  onShow(){
    clearInterval(this.data.timer);
    this.getIndexPage()

  }
});
Page(newInit);