var init = require('../../utils/init.js');
const app = getApp();
let goodsList = [
  { actEndTime: '2019-05-26 23:00:43' }]
var newInit = Object.assign({}, init)

newInit.data = Object.assign({}, newInit.data, {
  imageurl: app.globalData.imageurl,
  imgUrls: [
    'http://win2.qbt8.com/design/pg.png',
    'http://win2.qbt8.com/design/pg.png',
    'http://win2.qbt8.com/design/pg.png',
    'http://win2.qbt8.com/design/pg.png'
  ],
  interval: 3000,
  duration: 1000,
  vertical: false,
  indicatordots: true,
  autoplay: true,
  interval: 3000,
  duration: 1000,
  vertical: false,
  indicatordots: true,
  autoplay: true,
  color: "#52408F",
  showColor: "#Fff",
  scShow:false
});
newInit = Object.assign({}, newInit, {
  goto_login_telphone: function () {
    wx.navigateTo({
      url: '../login_telphone/login_telphone'
    })
  },
  tosc() {
    this.setData({
      scShow: this.data.scShow ? false : true
    })
  },
  getDate() {
    let endTimeList = [];
    // 将活动的结束时间参数提成一个单独的数组，方便操作
    goodsList.forEach(o => { endTimeList.push(o.actEndTime) })
    this.setData({ actEndTimeList: endTimeList });
    // 执行倒计时函数
    this.countDown();
  },
});
Page(newInit);
